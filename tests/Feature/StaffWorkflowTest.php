<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_uploads_a_document_and_associates_it_to_the_right_study(): void
    {
        $operator = $this->operator();
        $study = $this->study();
        $other = $this->study();

        $document = $this->upload($operator, for: $study);

        $this->assertSame(Document::STATUS_PENDING_ASSOCIATION, $document->status);
        $this->assertNull($document->study_id);
        Storage::disk('local')->assertExists($document->original_path);
        $this->assertStringNotContainsString('resultado', $document->original_path);

        $this->get(route('staff.documents.show', [$document, 'folio' => $study->folio]))
            ->assertOk()
            ->assertSee($study->folio)
            ->assertDontSee($other->folio);

        $this->post(route('staff.documents.associate', $document), ['study_id' => $study->id])
            ->assertSessionHasErrors('confirm');
        $this->assertNull($document->refresh()->study_id);

        $this->post(route('staff.documents.associate', $document), ['study_id' => $study->id, 'confirm' => '1'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame($study->id, $document->study_id);
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $document->status);
        $this->assertSame($operator->id, $document->associated_by);
        $this->assertTrue(AuditEvent::where('action', 'document.associated')->where('document_id', $document->id)->exists());
    }

    public function test_invalid_corrupt_or_oversized_files_are_rejected_safely(): void
    {
        $operator = $this->operator();
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        $files = [
            'texto con extensión pdf' => UploadedFile::fake()->createWithContent('resultado.pdf', 'esto no es un pdf'),
            'pdf truncado' => UploadedFile::fake()->createWithContent('resultado.pdf', '%PDF-1.4 incompleto'),
            'jpg corrupto' => UploadedFile::fake()->createWithContent('foto.jpg', $jpegHeader.str_repeat('x', 200)),
            'png renombrado a pdf' => UploadedFile::fake()->createWithContent('resultado.pdf', $this->pngBytes()),
            'ejecutable' => UploadedFile::fake()->createWithContent('resultado.exe', 'MZ'.str_repeat("\0", 100)),
            'sobredimensionado' => UploadedFile::fake()->create('resultado.pdf', config('eira.upload.max_kb') + 1, 'application/pdf'),
        ];

        foreach ($files as $case => $file) {
            $this->actingAs($operator)
                ->post(route('staff.documents.store'), $this->intakeFields($file))
                ->assertSessionHasErrors('file');
            $this->assertSame(0, Document::count(), $case);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_document_cannot_be_published_without_association_and_review(): void
    {
        $operator = $this->operator();
        $publisher = $this->publisher();
        $study = $this->study();
        $document = $this->upload($operator, for: $study);

        $this->actingAs($publisher)->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(Document::STATUS_PENDING_ASSOCIATION, $document->refresh()->status);

        $this->actingAs($operator)->post(route('staff.documents.associate', $document), ['study_id' => $study->id, 'confirm' => '1']);

        $this->actingAs($publisher)->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $document->refresh()->status);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), ['check_legible' => '1', 'check_matches_study' => '1'])
            ->assertSessionHasErrors('check_origin_authorization');
        $this->assertSame(Document::STATUS_PENDING_REVIEW, $document->refresh()->status);
        $this->assertSame(0, DeliveryNotification::count());
    }

    public function test_operator_and_admin_get_403_when_publishing_even_by_direct_route(): void
    {
        $document = $this->readyDocument($this->study());

        foreach ([$this->operator(), User::factory()->create(['role' => User::ROLE_ADMIN])] as $user) {
            $this->actingAs($user)->post(route('staff.documents.publish', $document), ['confirm' => '1'])->assertForbidden();
            $this->actingAs($user)->post(route('staff.documents.revoke', $document), ['reason' => 'other'])->assertForbidden();
        }

        $this->assertSame(Document::STATUS_READY, $document->refresh()->status);
        $this->assertTrue(AuditEvent::where('action', 'document.publish_denied')->where('result', 'denied')->exists());
    }

    public function test_publisher_cannot_upload_associate_or_review(): void
    {
        $publisher = $this->publisher();
        $study = $this->study();
        $document = $this->upload($this->operator());

        $this->actingAs($publisher)->post(route('staff.documents.store'), $this->intakeFields($this->pdfUpload('Z')))->assertForbidden();
        $this->actingAs($publisher)->post(route('staff.documents.associate', $document), ['study_id' => $study->id, 'confirm' => '1'])->assertForbidden();
        $this->actingAs($publisher)->post(route('staff.documents.review', $document), [])->assertForbidden();
    }

    public function test_authorized_publisher_publishes_only_after_review_and_origin_check(): void
    {
        $study = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525500000001', true]]);
        $document = $this->readyDocument($study);
        $publisher = $this->publisher();

        $this->assertTrue($document->check_origin_authorization);
        $this->assertNotNull($document->reviewed_at);

        $this->actingAs($publisher)->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(Document::STATUS_PUBLISHED, $document->status);
        $this->assertSame($publisher->id, $document->published_by);
        $this->assertNotSame($document->reviewed_by, $document->published_by);
        $this->assertSame(2, DeliveryNotification::where('document_id', $document->id)->where('status', 'accepted')->count());
    }

    public function test_publication_is_blocked_when_the_stored_file_no_longer_matches_its_hash(): void
    {
        $document = $this->readyDocument($this->study());
        Storage::disk('local')->put($document->delivery_path, 'contenido alterado');

        $this->actingAs($this->publisher())->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame(Document::STATUS_READY, $document->refresh()->status);
    }

    public function test_identical_active_file_is_not_duplicated_and_corrections_are_controlled_versions(): void
    {
        $operator = $this->operator();
        $study = $this->study();
        $first = $this->publishedDocument($study, $this->pdfUpload('original'));

        $this->actingAs($operator)
            ->post(route('staff.documents.store'), $this->intakeFields($this->pdfUpload('original'), for: $study))
            ->assertSessionHasErrors('file');
        $this->assertSame(1, Document::count());

        $correction = $this->upload($operator, $this->pdfUpload('correccion'), for: $study);
        $this->actingAs($operator)->post(route('staff.documents.associate', $correction), ['study_id' => $study->id, 'confirm' => '1'])
            ->assertSessionHasErrors('workflow');
        $this->assertNull($correction->refresh()->study_id);

        $this->actingAs($operator)->post(route('staff.documents.associate', $correction), [
            'study_id' => $study->id, 'confirm' => '1', 'confirm_new_version' => '1',
        ])->assertSessionHasNoErrors();

        $correction->refresh();
        $this->assertSame(2, $correction->version);
        $this->assertSame($first->id, $correction->supersedes_id);
        $this->assertSame(Document::STATUS_PUBLISHED, $first->refresh()->status);

        $this->actingAs($operator)->post(route('staff.documents.review', $correction), [
            'check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1',
        ]);
        $this->actingAs($this->publisher())->post(route('staff.documents.publish', $correction), ['confirm' => '1']);

        $this->assertSame(Document::STATUS_PUBLISHED, $correction->refresh()->status);
        $this->assertSame(Document::STATUS_REVOKED, $first->refresh()->status);
        $this->assertSame('superseded', $first->revocation_reason);
        Storage::disk('local')->assertExists($first->original_path);
    }

    public function test_notifications_are_not_duplicated_by_repeated_publication_or_dispatch(): void
    {
        $document = $this->publishedDocument($this->study());
        $this->assertSame(1, DeliveryNotification::count());

        $this->actingAs($this->publisher())->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasErrors('workflow');
        app(NotificationDispatcher::class)->notifyPublication($document);

        $this->assertSame(1, DeliveryNotification::count());
        $this->assertSame(1, DeliveryNotification::first()->attempts);
    }

    public function test_two_patients_with_the_same_eira_id_are_not_merged(): void
    {
        $a = $this->study(eiraId: 'DEMO-ABC-900101');
        $b = $this->study(eiraId: 'DEMO-ABC-900101');

        $this->assertNotSame($a->patient_id, $b->patient_id);
        $this->assertSame(2, Patient::where('external_eira_id', 'DEMO-ABC-900101')->count());

        $document = $this->publishedDocument($a);
        $this->assertSame($a->id, $document->study_id);
        $this->assertSame(0, $b->documents()->count());
    }

    public function test_image_is_stored_as_received_and_converted_to_pdf_only_after_review(): void
    {
        $operator = $this->operator();
        $study = $this->study();
        $document = $this->upload($operator, UploadedFile::fake()->createWithContent('foto.png', $this->pngBytes()), for: $study);

        $this->assertSame('image/png', $document->original_mime);
        $this->assertNull($document->delivery_path);

        $this->actingAs($operator)->post(route('staff.documents.associate', $document), ['study_id' => $study->id, 'confirm' => '1']);
        $this->assertNull($document->refresh()->delivery_path);

        $this->actingAs($operator)->post(route('staff.documents.review', $document), [
            'check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($document->delivery_path));
        $this->assertSame($document->sha256, hash('sha256', Storage::disk('local')->get($document->original_path)));
    }

    public function test_staff_routes_require_authentication_and_no_public_route_serves_documents(): void
    {
        $document = $this->publishedDocument($this->study());
        $this->asPatient();

        foreach ([
            route('staff.dashboard'),
            route('staff.intake'),
            route('staff.publication'),
            route('staff.documents.show', $document),
            route('staff.documents.file', $document),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->post(route('staff.documents.publish', $document))->assertRedirect(route('login'));
        $this->get('/storage/'.$document->original_path)->assertNotFound();
        $this->get('/'.$document->original_path)->assertNotFound();

        $this->assertFalse(file_exists(public_path('storage')));
        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);

        $this->actingAs(User::factory()->create(['role' => 'none']))->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_staff_pages_render_without_exposing_contact_values(): void
    {
        $study = $this->study([['email', 'paciente.demo@eira.example', true], ['sms', '+525500000000', true]]);
        $document = $this->publishedDocument($study);

        foreach ([$this->operator(), $this->publisher()] as $user) {
            foreach ([route('staff.dashboard'), route('staff.intake'), route('staff.publication'), route('staff.documents.show', $document)] as $url) {
                $this->actingAs($user)->get($url)->assertOk()->assertDontSee('paciente.demo@eira.example')->assertDontSee('+525500000000');
            }
        }

        $this->actingAs($this->operator())->get(route('staff.documents.file', $document))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(60, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
