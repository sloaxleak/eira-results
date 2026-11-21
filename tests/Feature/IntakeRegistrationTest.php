<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Study;
use App\Models\StudyContact;
use App\Services\Notifications\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntakeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_without_preloaded_data_registers_the_study_from_the_form(): void
    {
        $operator = $this->operator();

        $document = $this->upload($operator, overrides: [
            'form_folio' => ' 104 512 ',
            'eira_id' => 'jaca990214',
            'cytology_number' => 'c-77',
            'taken_at' => now()->subDays(3)->toDateString(),
            'phone' => '55 1234 5678',
        ]);

        $study = $document->study;
        $this->assertNotNull($study);
        $this->assertMatchesRegularExpression('/^EIRA-\d{4}-\d{5}$/', $study->folio);
        $this->assertSame('104512', $study->external_form_folio);
        $this->assertSame('C-77', $study->external_cytology_number);
        $this->assertSame('JACA990214', $study->patient->external_eira_id);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $document->status);
        $this->assertSame($operator->id, $document->associated_by);

        $contact = $study->contacts()->sole();
        $this->assertSame('sms', $contact->channel);
        $this->assertSame('+525512345678', $contact->value);
        $this->assertNull($contact->verified_at);
        $this->assertTrue(AuditEvent::where('action', 'study.registered')->where('study_id', $study->id)->exists());
    }

    public function test_result_and_optional_prescription_are_received_together_but_published_separately(): void
    {
        $operator = $this->operator();
        $known = Document::pluck('id');

        $this->actingAs($operator)->post(route('staff.documents.store'), $this->intakeFields(
            $this->pdfUpload('resultado'),
            overrides: ['prescription' => $this->pdfUpload('receta'), 'phone' => '5512345678'],
        ))->assertSessionHasNoErrors();

        $documents = Document::whereNotIn('id', $known)->get()->keyBy('type');
        $result = $documents['result'];
        $prescription = $documents['prescription'];

        $this->assertNotNull($result->study_id);
        $this->assertSame($result->study_id, $prescription->study_id);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $result->status);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $prescription->status);
        $this->assertSame(1, $result->version);
        $this->assertSame(1, $prescription->version);

        $this->actingAs($operator)->get(route('staff.documents.show', $result))->assertOk()
            ->assertSee('Otros documentos del estudio')
            ->assertSee($prescription->shortId());

        $this->actingAs($operator)->post(route('staff.documents.review', $result), [
            'check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1',
        ]);
        $this->actingAs($this->publisher())->post(route('staff.documents.publish', $result), ['confirm' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(Document::STATUS_PUBLISHED, $result->refresh()->status);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $prescription->refresh()->status);
    }

    public function test_invalid_or_identical_prescription_rejects_the_whole_intake(): void
    {
        $operator = $this->operator();
        $result = $this->pdfUpload('mismo');

        foreach ([
            UploadedFile::fake()->createWithContent('receta.pdf', 'no es pdf'),
            UploadedFile::fake()->createWithContent('receta.pdf', file_get_contents($result->getRealPath())),
        ] as $badPrescription) {
            $this->actingAs($operator)
                ->post(route('staff.documents.store'), $this->intakeFields($result, overrides: ['prescription' => $badPrescription]))
                ->assertSessionHasErrors('prescription');
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Study::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_with_existing_form_folio_both_documents_wait_for_manual_association(): void
    {
        $operator = $this->operator();
        $this->upload($operator, overrides: ['form_folio' => '880001']);
        $known = Document::pluck('id');

        $this->actingAs($operator)->post(route('staff.documents.store'), $this->intakeFields(
            overrides: ['form_folio' => '880001', 'prescription' => $this->pdfUpload('receta-2')],
        ))->assertSessionHasNoErrors();

        $new = Document::whereNotIn('id', $known)->get();
        $this->assertCount(2, $new);
        $this->assertTrue($new->every(fn ($d) => $d->study_id === null && $d->status === Document::STATUS_PENDING_ASSOCIATION));
        $this->assertSame(1, Study::count());
    }

    public function test_repeated_form_folio_is_never_associated_automatically(): void
    {
        $operator = $this->operator();
        $first = $this->upload($operator, overrides: ['form_folio' => '555001']);
        $second = $this->upload($operator, overrides: ['form_folio' => '555001', 'eira_id' => 'GOMA850505']);

        $this->assertSame(1, Study::count());
        $this->assertSame($first->study_id, Study::first()->id);
        $this->assertNull($second->study_id);
        $this->assertSame(Document::STATUS_PENDING_ASSOCIATION, $second->status);
        $this->assertSame(1, Patient::count());
    }

    public function test_required_form_data_and_eira_id_format_are_validated(): void
    {
        $operator = $this->operator();

        foreach ([
            ['form_folio' => ''],
            ['eira_id' => ''],
            ['eira_id' => 'JACA991314'],
            ['eira_id' => 'JAC990214'],
            ['phone' => '12345'],
            ['taken_at' => now()->addDay()->toDateString()],
            ['confirm' => null],
        ] as $override) {
            $this->actingAs($operator)
                ->post(route('staff.documents.store'), $this->intakeFields(overrides: $override))
                ->assertSessionHasErrors(array_key_first($override) === 'confirm' ? 'confirm' : array_key_first($override));
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Study::count());
    }

    public function test_transcribed_contact_receives_code_only_after_staff_confirmation(): void
    {
        $operator = $this->operator();
        $unconfirmed = $this->upload($operator, overrides: ['phone' => '5511112222']);
        $confirmed = $this->upload($operator, overrides: ['phone' => '5533334444']);
        $contact = StudyContact::where('study_id', $confirmed->study_id)->sole();

        $this->actingAs($this->publisher())->post(route('staff.contacts.verify', $contact))->assertForbidden();
        $this->actingAs($operator)->post(route('staff.contacts.verify', $contact))->assertSessionHasNoErrors();
        $this->assertNotNull($contact->refresh()->verified_at);

        foreach ([$unconfirmed, $confirmed] as $document) {
            $this->actingAs($operator)->post(route('staff.documents.review', $document), [
                'check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1',
            ])->assertSessionHasNoErrors();
            $this->actingAs($this->publisher())->post(route('staff.documents.publish', $document), ['confirm' => '1'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(0, DeliveryNotification::where('study_id', $unconfirmed->study_id)->count());
        $sent = app(SmsSender::class)->sent;
        $this->assertCount(1, $sent);
        $this->assertSame('+525533334444', $sent[0]['to']);

        preg_match('/recibido por SMS ([A-Z0-9]{6})/u', $sent[0]['text'], $code);
        $this->redeem($confirmed->study, $code[1])->assertRedirect(route('portal.study'));
        $this->assertTrue(AuditEvent::where('action', 'contact.verified')->where('study_id', $confirmed->study_id)->exists());
    }

    public function test_patient_with_repeated_form_folio_only_reaches_the_study_matching_her_code(): void
    {
        $a = $this->study([['sms', '+525500000011', true]]);
        $b = $this->study([['sms', '+525500000022', true]]);
        $b->update(['external_form_folio' => $a->external_form_folio]);

        $docA = $this->publishedDocument($a);
        $docB = $this->publishedDocument($b->refresh());

        $this->redeem($b, $this->codeFor($b, 'sms'))->assertRedirect(route('portal.study'));
        $this->get(route('portal.document', [$docB->id, 'ver']))->assertOk();
        $this->get(route('portal.document', [$docA->id, 'ver']))->assertNotFound();
    }
}
