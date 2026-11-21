<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Models\StudyContact;
use App\Services\Notifications\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishOnReviewTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKS = ['check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['eira.publish_on_review' => true]);
    }

    public function test_confirming_the_review_publishes_and_notifies_in_one_step(): void
    {
        $operator = $this->operator();
        $document = $this->upload($operator, overrides: ['phone' => '5512345678']);

        $this->actingAs($operator)->get(route('staff.documents.show', $document))->assertOk()
            ->assertSee('Confirmar, publicar y notificar')
            ->assertSee('pertenece(n) a la paciente', false);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), self::CHECKS + ['confirm_contacts' => '1'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(Document::STATUS_PUBLISHED, $document->status);
        $this->assertSame($operator->id, $document->reviewed_by);
        $this->assertSame($operator->id, $document->published_by);
        $this->assertNotNull(StudyContact::where('study_id', $document->study_id)->sole()->verified_at);
        $this->assertSame('accepted', DeliveryNotification::where('document_id', $document->id)->sole()->status);
        $this->assertSame('+525512345678', app(SmsSender::class)->sent[0]['to']);
        $this->assertTrue(AuditEvent::where('action', 'contact.verified')->where('meta->method', 'review')->exists());

        preg_match('/recibido por SMS ([A-Z0-9]{6})/u', app(SmsSender::class)->sent[0]['text'], $code);
        $this->redeem($document->study, $code[1])->assertRedirect(route('portal.study'));
    }

    public function test_without_confirming_contacts_it_publishes_but_sends_nothing(): void
    {
        $operator = $this->operator();
        $document = $this->upload($operator, overrides: ['phone' => '5512345678']);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), self::CHECKS)->assertSessionHasNoErrors();

        $this->assertSame(Document::STATUS_PUBLISHED, $document->refresh()->status);
        $this->assertSame(0, DeliveryNotification::count());
    }

    public function test_review_checks_are_still_required_and_publisher_mode_still_works(): void
    {
        $operator = $this->operator();
        $document = $this->upload($operator);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), ['check_legible' => '1'])
            ->assertSessionHasErrors(['check_matches_study', 'check_origin_authorization']);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $document->refresh()->status);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), self::CHECKS);
        $this->actingAs($operator)->post(route('staff.documents.revoke', $document), ['reason' => 'publication_error'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Document::STATUS_REVOKED, $document->refresh()->status);
    }
}
