<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\PortalController;
use App\Http\Middleware\EnsurePatientStudySession;
use App\Models\AccessChallenge;
use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Services\Documents\DocumentWorkflow;
use App\Services\Notifications\EmailSender;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_publish_notify_redeem_view_and_download(): void
    {
        $study = $this->study();
        $document = $this->publishedDocument($study);

        $message = app(EmailSender::class)->sent[0]['text'];
        $this->assertSame(1, preg_match('/recibido por (?:correo|SMS) ([A-Z0-9]{6})/u', $message, $matches));
        $this->assertStringNotContainsString($study->external_form_folio, $message);
        $this->assertStringNotContainsStringIgnoringCase('papanicolaou', $message);

        $this->redeem($study, $matches[1])->assertRedirect(route('portal.study'));

        $this->get(route('portal.study'))->assertOk()->assertSee('Documento disponible');

        $view = $this->get(route('portal.document', [$document->id, 'ver']))->assertOk();
        $this->assertStringContainsString('no-store', $view->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $view->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $view->streamedContent());

        $download = $this->get(route('portal.document', [$document->id, 'descargar']))->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));

        $this->assertTrue(AuditEvent::where('action', 'document.viewed')->where('study_id', $study->id)->exists());
        $this->assertTrue(AuditEvent::where('action', 'document.downloaded')->where('study_id', $study->id)->exists());
    }

    public function test_before_publication_the_patient_cannot_access_and_the_code_is_not_consumed(): void
    {
        $study = $this->study();
        $document = $this->readyDocument($study);
        $code = $this->codeFor($study);

        $this->redeem($study, $code)->assertSessionHasErrors('access');
        $this->get(route('portal.study'))->assertRedirect(route('portal.show'));
        $this->get(route('portal.document', [$document->id, 'descargar']))->assertRedirect(route('portal.show'));

        $challenge = AccessChallenge::where('study_id', $study->id)->first();
        $this->assertNull($challenge->consumed_at);
        $this->assertSame(0, $challenge->attempts);

        $this->withSession([EnsurePatientStudySession::SESSION_KEY => ['study_id' => $study->id, 'expires_at' => now()->addMinutes(10)->timestamp]])
            ->get(route('portal.document', [$document->id, 'descargar']))
            ->assertNotFound();
    }

    public function test_responses_do_not_reveal_whether_a_reference_exists(): void
    {
        $study = $this->study();
        $this->publishedDocument($study);

        $unpublished = $this->study();
        $this->readyDocument($unpublished);
        $neutral = ['access' => PortalController::NEUTRAL_ERROR];

        $this->asPatient()->post(route('portal.redeem'), ['folio' => 'EIRA-TEST-9999', 'code' => 'ABC234'])
            ->assertSessionHasErrors($neutral);
        $this->redeem($study, 'ZZZZZZ')->assertSessionHasErrors($neutral);
        $this->redeem($unpublished, $this->codeFor($unpublished))->assertSessionHasErrors($neutral);
    }

    public function test_session_for_study_a_cannot_reach_documents_of_study_b(): void
    {
        $studyA = $this->study();
        $studyB = $this->study();
        $documentA = $this->publishedDocument($studyA);
        $documentB = $this->publishedDocument($studyB);

        $this->redeem($studyA, $this->codeFor($studyA))->assertRedirect(route('portal.study'));

        $this->get(route('portal.document', [$documentA->id, 'ver']))->assertOk();
        $this->get(route('portal.document', [$documentB->id, 'ver']))->assertNotFound();
        $this->get(route('portal.document', [$documentB->id, 'descargar']))->assertNotFound();
        $this->get(route('portal.study'))->assertOk()->assertSee($studyA->external_form_folio)->assertDontSee($studyB->external_form_folio);

        $this->get(route('staff.documents.file', $documentB))->assertRedirect(route('login'));
        $this->assertFalse(AuditEvent::whereIn('action', ['document.viewed', 'document.downloaded'])->where('study_id', $studyB->id)->exists());
    }

    public function test_revoked_document_stops_being_downloadable_on_the_next_request(): void
    {
        $study = $this->study();
        $document = $this->publishedDocument($study);

        $this->redeem($study, $this->codeFor($study))->assertRedirect(route('portal.study'));
        $this->get(route('portal.document', [$document->id, 'descargar']))->assertOk();

        $this->actingAs($this->publisher())->post(route('staff.documents.revoke', $document), ['reason' => 'publication_error'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Document::STATUS_REVOKED, $document->refresh()->status);

        $this->asPatient();
        $this->get(route('portal.document', [$document->id, 'descargar']))->assertNotFound();
        $this->get(route('portal.document', [$document->id, 'ver']))->assertNotFound();
    }

    public function test_code_is_redeemed_once_and_reuse_is_rejected(): void
    {
        $study = $this->study();
        $this->publishedDocument($study);
        $code = $this->codeFor($study);

        $this->redeem($study, $code)->assertRedirect(route('portal.study'));
        $this->flushSession();
        $this->redeem($study, $code)->assertSessionHasErrors('access');
        $this->get(route('portal.study'))->assertRedirect(route('portal.show'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $study = $this->study();
        $this->publishedDocument($study);
        $code = $this->codeFor($study);

        $this->travel(config('eira.otp.ttl_minutes') + 1)->minutes();

        $this->redeem($study, $code)->assertSessionHasErrors('access');
    }

    public function test_code_is_locked_after_too_many_wrong_attempts(): void
    {
        $study = $this->study();
        $this->publishedDocument($study);
        $code = $this->codeFor($study);

        for ($i = 0; $i < config('eira.otp.max_attempts'); $i++) {
            $this->redeem($study, 'ZZZZZZ')->assertSessionHasErrors('access');
        }

        $this->redeem($study, $code)->assertSessionHasErrors('access');
        $this->get(route('portal.study'))->assertRedirect(route('portal.show'));
    }

    public function test_codes_are_independent_per_channel_and_redeeming_one_revokes_the_others(): void
    {
        $study = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525500000001', true]]);
        $this->publishedDocument($study);

        preg_match('/recibido por correo ([A-Z0-9]{6})/u', app(EmailSender::class)->sent[0]['text'], $email);
        preg_match('/recibido por SMS ([A-Z0-9]{6})/u', app(SmsSender::class)->sent[0]['text'], $sms);
        $this->assertNotSame($email[1], $sms[1]);

        $this->redeem($study, $sms[1])->assertRedirect(route('portal.study'));
        $this->flushSession();
        $this->redeem($study, $email[1])->assertSessionHasErrors('access');
        $this->assertSame(0, AccessChallenge::where('study_id', $study->id)->redeemable()->count());
    }

    public function test_failed_or_disabled_notifications_do_not_block_publication_or_valid_access(): void
    {
        $this->useSmsDriver('disabled');

        $disabledSms = $this->study([['email', 'fallo.demo@eira.example', true], ['sms', '+525500000001', true]]);
        $document = $this->publishedDocument($disabledSms);

        $this->assertSame(Document::STATUS_PUBLISHED, $document->status);
        $this->assertSame('failed', DeliveryNotification::where('study_id', $disabledSms->id)->where('channel', 'email')->value('status'));
        $this->assertSame('disabled', DeliveryNotification::where('study_id', $disabledSms->id)->where('channel', 'sms')->value('status'));
        $this->assertSame(0, AccessChallenge::where('study_id', $disabledSms->id)->redeemable()->count());

        $this->redeem($disabledSms, $this->codeFor($disabledSms))->assertRedirect(route('portal.study'));
        $this->get(route('portal.document', [$document->id, 'descargar']))->assertOk();

        $this->useSmsDriver('fake');

        $failedSms = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525500000000', true]]);
        $this->assertSame(Document::STATUS_PUBLISHED, $this->publishedDocument($failedSms)->status);

        $this->assertSame('failed', DeliveryNotification::where('study_id', $failedSms->id)->where('channel', 'sms')->value('status'));
        $this->assertSame('accepted', DeliveryNotification::where('study_id', $failedSms->id)->where('channel', 'email')->value('status'));
    }

    public function test_unverified_contacts_never_receive_codes(): void
    {
        $study = $this->study([['sms', '+525500000003', false]]);
        $this->publishedDocument($study);

        $this->assertSame(0, DeliveryNotification::count());
        $this->assertSame(0, AccessChallenge::count());
        $this->assertSame([], app(SmsSender::class)->sent);
    }

    public function test_empty_or_malformed_contacts_fail_without_issuing_a_code(): void
    {
        $study = $this->study([['email', '', true], ['sms', 'no-es-telefono', true]]);
        $document = $this->publishedDocument($study);

        $this->assertSame(Document::STATUS_PUBLISHED, $document->status);
        $this->assertSame(2, DeliveryNotification::where('status', 'failed')->where('last_error', 'invalid_contact')->count());
        $this->assertSame(0, AccessChallenge::count());
        $this->assertSame([], app(EmailSender::class)->sent);
        $this->assertSame([], app(SmsSender::class)->sent);
    }

    public function test_code_resend_is_neutral_goes_only_to_linked_contacts_and_respects_cooldown(): void
    {
        $study = $this->study();
        $this->publishedDocument($study);
        $this->asPatient();

        $this->post(route('portal.resend'), ['folio' => 'EIRA-TEST-9999', 'contact' => 'intruso@eira.example']);
        $unknownMessage = session('status');
        $this->post(route('portal.resend'), ['folio' => $study->external_form_folio, 'contact' => 'intruso@eira.example']);

        $this->assertNotEmpty($unknownMessage);
        $this->assertSame($unknownMessage, session('status'));
        $this->assertCount(1, app(EmailSender::class)->sent);

        $this->travel(config('eira.otp.resend_cooldown_seconds') + 5)->seconds();
        $this->post(route('portal.resend'), ['folio' => $study->external_form_folio, 'contact' => 'intruso@eira.example'])
            ->assertSessionHas('status');

        $sent = app(EmailSender::class)->sent;
        $this->assertCount(2, $sent);
        $this->assertSame('paciente.demo@eira.example', $sent[1]['to']);

        preg_match('/recibido por correo ([A-Z0-9]{6})/u', $sent[0]['text'], $old);
        preg_match('/recibido por correo ([A-Z0-9]{6})/u', $sent[1]['text'], $new);
        $this->redeem($study, $old[1])->assertSessionHasErrors('access');
        $this->redeem($study, $new[1])->assertRedirect(route('portal.study'));
    }

    public function test_patient_session_expires(): void
    {
        $study = $this->study();
        $document = $this->publishedDocument($study);

        $this->redeem($study, $this->codeFor($study))->assertRedirect(route('portal.study'));
        $this->travel(config('eira.patient_session_minutes') + 1)->minutes();

        $this->get(route('portal.document', [$document->id, 'descargar']))->assertRedirect(route('portal.show'));
    }

    public function test_audit_log_never_stores_codes_contacts_or_message_bodies(): void
    {
        $study = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525500000001', true]]);
        $this->publishedDocument($study);
        $code = $this->codeFor($study);
        $this->redeem($study, $code);

        $dump = AuditEvent::all()->toJson().DeliveryNotification::all()->toJson().AccessChallenge::all()->makeVisible('code_hash')->toJson();

        $this->assertStringNotContainsString($code, $dump);
        $this->assertStringNotContainsString('paciente.demo@eira.example', $dump);
        $this->assertStringNotContainsString('+525500000001', $dump);
        $this->assertStringNotContainsString('recibido por', $dump);
    }

    private function useSmsDriver(string $driver): void
    {
        config(['eira.sms_driver' => $driver]);

        foreach ([SmsSender::class, NotificationDispatcher::class, DocumentWorkflow::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }
}
