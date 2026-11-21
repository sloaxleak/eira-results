<?php

namespace App\Services\Access;

use App\Models\AccessChallenge;
use App\Models\Study;
use App\Services\Audit;
use Illuminate\Support\Facades\Hash;

class AccessCodes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private static ?string $dummyHash = null;

    /**
     * Emite un código para el estudio y canal; invalida los códigos previos sin usar de ese canal.
     *
     * @return array{0: AccessChallenge, 1: string} desafío y código en claro (solo para entregarlo al transporte)
     */
    public function issue(Study $study, string $channel): array
    {
        $code = '';
        for ($i = 0; $i < config('eira.otp.length'); $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        AccessChallenge::where('study_id', $study->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $challenge = AccessChallenge::create([
            'study_id' => $study->id,
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('eira.otp.ttl_minutes')),
        ]);

        Audit::record('access.code_issued', 'ok', $study->id, null, ['channel' => $channel]);

        return [$challenge, $code];
    }

    /** Vigencia legible del código, p. ej. «7 días», «2 horas», «15 minutos». */
    public static function ttlLabel(): string
    {
        $minutes = (int) config('eira.otp.ttl_minutes');
        [$value, $one, $many] = match (true) {
            $minutes >= 1440 && $minutes % 1440 === 0 => [intdiv($minutes, 1440), 'día', 'días'],
            $minutes >= 60 && $minutes % 60 === 0 => [intdiv($minutes, 60), 'hora', 'horas'],
            default => [$minutes, 'minuto', 'minutos'],
        };

        return $value.' '.($value === 1 ? $one : $many);
    }

    public function normalize(string $code): string
    {
        return mb_strtoupper(preg_replace('/[\s\-]+/', '', $code));
    }

    /** Estudios con documento publicado cuyo folio de formato coincide (el folio escrito a mano podría repetirse). */
    public function studiesForFormFolio(string $folio)
    {
        return Study::where('external_form_folio', Study::normalizeFolio($folio))->get()
            ->filter(fn (Study $study) => $study->hasPublishedDocument())
            ->values();
    }

    /**
     * Canjea folio del formato + código. Devuelve el estudio solo si hay documento publicado y el
     * código se consumió atómicamente; en cualquier otro caso devuelve null sin distinguir el motivo.
     */
    public function redeem(string $folio, string $code): ?Study
    {
        $code = $this->normalize($code);
        $studies = $this->studiesForFormFolio($folio);

        if ($studies->isEmpty()) {
            Hash::check($code, self::$dummyHash ??= Hash::make('eira-dummy'));
            Audit::record('access.failed', 'denied', null, null, [], 'patient');

            return null;
        }

        $challenges = AccessChallenge::whereIn('study_id', $studies->modelKeys())->redeemable()->get();

        foreach ($challenges as $challenge) {
            if (! Hash::check($code, $challenge->code_hash)) {
                continue;
            }

            $consumed = AccessChallenge::whereKey($challenge->id)->redeemable()->update(['consumed_at' => now()]);

            if ($consumed === 1) {
                AccessChallenge::where('study_id', $challenge->study_id)
                    ->whereKeyNot($challenge->id)
                    ->whereNull('consumed_at')
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                Audit::record('access.redeemed', 'ok', $challenge->study_id, null, ['channel' => $challenge->channel], 'patient');

                return $studies->firstWhere('id', $challenge->study_id);
            }
        }

        if ($challenges->isNotEmpty()) {
            AccessChallenge::whereIn('id', $challenges->modelKeys())->increment('attempts');
        }

        Audit::record('access.failed', 'denied', $studies->count() === 1 ? $studies->first()->id : null, null, [], 'patient');

        return null;
    }
}
