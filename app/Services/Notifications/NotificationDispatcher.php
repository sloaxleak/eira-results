<?php

namespace App\Services\Notifications;

use App\Models\AccessChallenge;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Models\Study;
use App\Models\StudyContact;
use App\Services\Access\AccessCodes;
use App\Services\Audit;
use Throwable;

class NotificationDispatcher
{
    public function __construct(
        private EmailSender $email,
        private SmsSender $sms,
        private AccessCodes $codes,
    ) {}

    /**
     * Avisa por cada contacto verificado del estudio con un código independiente por canal.
     * Idempotente por documento + versión + contacto: repetir la llamada no reenvía.
     */
    public function notifyPublication(Document $document): void
    {
        $study = $document->study;

        foreach ($this->verifiedContacts($study) as $contact) {
            $notification = DeliveryNotification::firstOrCreate(
                ['idempotency_key' => "publication:{$document->id}:v{$document->version}:{$contact->id}"],
                [
                    'study_id' => $study->id,
                    'document_id' => $document->id,
                    'study_contact_id' => $contact->id,
                    'channel' => $contact->channel,
                    'purpose' => 'publication',
                    'status' => DeliveryNotification::STATUS_PENDING,
                ],
            );

            if ($notification->wasRecentlyCreated) {
                $this->attempt($notification, $contact);
            }
        }
    }

    /**
     * Reenvío solicitado desde el portal: solo a contactos ya vinculados y verificados,
     * con enfriamiento por canal. No revela si hubo o no envío.
     */
    public function resendCode(Study $study): void
    {
        if (! $study->hasPublishedDocument()) {
            return;
        }

        $cooldown = max(1, (int) config('eira.otp.resend_cooldown_seconds'));

        foreach ($this->verifiedContacts($study) as $contact) {
            $recent = AccessChallenge::where('study_id', $study->id)
                ->where('channel', $contact->channel)
                ->where('created_at', '>', now()->subSeconds($cooldown))
                ->exists();

            if ($recent) {
                continue;
            }

            $notification = DeliveryNotification::firstOrCreate(
                ['idempotency_key' => "resend:{$contact->id}:".intdiv(now()->timestamp, $cooldown)],
                [
                    'study_id' => $study->id,
                    'study_contact_id' => $contact->id,
                    'channel' => $contact->channel,
                    'purpose' => 'resend',
                    'status' => DeliveryNotification::STATUS_PENDING,
                ],
            );

            if ($notification->wasRecentlyCreated) {
                $this->attempt($notification, $contact);
            }
        }
    }

    /** Reintento explícito por personal; solo para avisos fallidos cuyo contacto sigue verificado. */
    public function retry(DeliveryNotification $notification): bool
    {
        $contact = $notification->contact;

        if ($notification->status !== DeliveryNotification::STATUS_FAILED || ! $contact?->verified_at) {
            return false;
        }

        $this->attempt($notification, $contact);

        return true;
    }

    private function verifiedContacts(Study $study)
    {
        return $study->contacts()->whereNotNull('verified_at')->orderBy('channel')->get();
    }

    private function attempt(DeliveryNotification $notification, StudyContact $contact): void
    {
        $valid = $contact->channel === StudyContact::CHANNEL_EMAIL
            ? filter_var($contact->value, FILTER_VALIDATE_EMAIL) !== false
            : preg_match('/^\+?\d{10,15}$/', (string) $contact->value) === 1;

        if (! $valid) {
            $notification->update([
                'status' => DeliveryNotification::STATUS_FAILED,
                'attempts' => $notification->attempts + 1,
                'last_error' => 'invalid_contact',
                'last_attempt_at' => now(),
            ]);
            Audit::record('notification.attempt', 'failed', $notification->study_id, $notification->document_id, [
                'channel' => $contact->channel,
                'reason' => 'invalid_contact',
            ]);

            return;
        }

        [$challenge, $code] = $this->codes->issue($notification->study, $contact->channel);
        $key = $notification->idempotency_key.':'.($notification->attempts + 1);
        $text = $this->message($code, $contact->channel);

        try {
            $result = $contact->channel === StudyContact::CHANNEL_EMAIL
                ? $this->email->send($contact->value, self::EMAIL_SUBJECT, $text, $key, self::emailHtml($code))
                : $this->sms->send($contact->value, $text, $key);
        } catch (Throwable $e) {
            $result = SendResult::failed('transport_exception');
        }

        if ($result->status !== DeliveryNotification::STATUS_ACCEPTED) {
            $challenge->update(['revoked_at' => now()]);
        }

        $notification->update([
            'status' => $result->status,
            'provider_message_id' => $result->providerId,
            'attempts' => $notification->attempts + 1,
            'last_error' => $result->error,
            'last_attempt_at' => now(),
        ]);

        Audit::record('notification.attempt', $result->status, $notification->study_id, $notification->document_id, [
            'channel' => $contact->channel,
            'purpose' => $notification->purpose,
        ]);
    }

    public const EMAIL_SUBJECT = 'EIRA: tienes una actualización de tu estudio';

    /** Correo HTML neutro: sin diagnóstico, tipo de estudio, folio ni enlace personal. */
    public static function emailHtml(string $code): string
    {
        return view('mail.notification', [
            'code' => $code,
            'portalUrl' => config('eira.portal_url'),
            'ttl' => AccessCodes::ttlLabel(),
        ])->render();
    }

    private function message(string $code, string $channel): string
    {
        return sprintf(
            'EIRA: Tenemos una actualización de tu estudio. Consulta %s con tu folio y el código recibido por %s %s. Vence en %s.',
            config('eira.portal_url'),
            $channel === StudyContact::CHANNEL_SMS ? 'SMS' : 'correo',
            $code,
            AccessCodes::ttlLabel(),
        );
    }
}
