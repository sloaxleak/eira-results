<?php

namespace Tests\Feature;

use App\Models\AccessChallenge;
use App\Models\DeliveryNotification;
use App\Models\StudyContact;
use App\Services\Access\AccessCodes;
use App\Services\Notifications\EmailSender;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_codes_last_seven_days_by_default_and_are_labelled_accordingly(): void
    {
        $this->assertSame(10080, config('eira.otp.ttl_minutes'));
        $this->assertSame('7 días', AccessCodes::ttlLabel());

        $study = $this->study();
        $this->publishedDocument($study);
        $challenge = AccessChallenge::where('study_id', $study->id)->sole();

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $challenge->expires_at->timestamp, 5);
        $this->assertStringContainsString('Vence en 7 días', app(EmailSender::class)->sent[0]['text']);
        $this->get(route('portal.show'))->assertSee('vence 7 días después de recibirlo');

        config(['eira.otp.ttl_minutes' => 15]);
        $this->assertSame('15 minutos', AccessCodes::ttlLabel());
        config(['eira.otp.ttl_minutes' => 60]);
        $this->assertSame('1 hora', AccessCodes::ttlLabel());
    }

    public function test_email_has_neutral_html_with_the_code_and_no_clinical_data_or_folio(): void
    {
        $study = $this->study([['email', 'paciente.demo@eira.example', true]]);
        $this->publishedDocument($study);

        $sent = app(EmailSender::class)->sent[0];
        preg_match('/recibido por correo ([A-Z0-9]{6})/u', $sent['text'], $code);

        $this->assertSame(NotificationDispatcher::EMAIL_SUBJECT, $sent['subject']);
        $this->assertNotNull($sent['html']);
        $this->assertStringContainsString($code[1], $sent['html']);
        $this->assertStringContainsString(config('eira.portal_url'), $sent['html']);
        $this->assertStringContainsString('vence 7 días', $sent['html']);
        foreach ([$study->external_form_folio, $study->folio, 'Papanicolaou', 'citolog', 'diagn'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $sent['html']);
        }

        $this->actingAs($this->operator())->get(route('staff.email-preview'))->assertOk()
            ->assertSee('EJEMPL')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->asPatient()->get(route('staff.email-preview'))->assertRedirect(route('login'));
    }

    public function test_contact_confirmed_after_publication_is_notified_once_without_resending_to_others(): void
    {
        $operator = $this->operator();
        $study = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525511112222', false]]);
        $this->publishedDocument($study);

        $this->assertCount(1, app(EmailSender::class)->sent);
        $this->assertSame([], app(SmsSender::class)->sent);

        $sms = StudyContact::where('study_id', $study->id)->where('channel', 'sms')->sole();
        $this->actingAs($operator)->post(route('staff.contacts.verify', $sms))->assertSessionHas('status');
        $this->actingAs($operator)->post(route('staff.contacts.verify', $sms));

        $this->assertCount(1, app(SmsSender::class)->sent);
        $this->assertSame('+525511112222', app(SmsSender::class)->sent[0]['to']);
        $this->assertCount(1, app(EmailSender::class)->sent);
        $this->assertSame(2, DeliveryNotification::where('study_id', $study->id)->count());
    }

    public function test_confirming_a_contact_before_publication_sends_nothing(): void
    {
        $operator = $this->operator();
        $document = $this->upload($operator, overrides: ['phone' => '5533334444']);
        $contact = StudyContact::where('study_id', $document->study_id)->sole();

        $this->actingAs($operator)->post(route('staff.contacts.verify', $contact));

        $this->assertSame(0, DeliveryNotification::count());
        $this->assertSame([], app(SmsSender::class)->sent);
    }
}
