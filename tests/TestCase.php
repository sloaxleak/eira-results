<?php

namespace Tests;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Study;
use App\Models\StudyContact;
use App\Models\User;
use App\Services\Access\AccessCodes;
use App\Support\PdfWriter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    protected function operator(): User
    {
        return User::factory()->create(['role' => User::ROLE_OPERATOR]);
    }

    protected function publisher(): User
    {
        return User::factory()->create(['role' => User::ROLE_PUBLISHER]);
    }

    protected function asPatient(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    /** @param list<array{0: string, 1: string, 2: bool}> $contacts canal, valor, verificado */
    protected function study(array $contacts = [['email', 'paciente.demo@eira.example', true]], ?string $eiraId = 'PELU900101'): Study
    {
        static $sequence = 0;
        $sequence++;

        $study = Study::create([
            'patient_id' => Patient::create(['external_eira_id' => $eiraId])->id,
            'folio' => sprintf('EIRA-TEST-%04d', $sequence),
            'external_form_folio' => sprintf('F-TEST-%04d', $sequence),
        ]);

        foreach ($contacts as [$channel, $value, $verified]) {
            StudyContact::create([
                'study_id' => $study->id,
                'channel' => $channel,
                'value' => $value,
                'verified_at' => $verified ? now() : null,
            ]);
        }

        return $study;
    }

    protected function pdfUpload(string $marker = 'A'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent("resultado-{$marker}.pdf", PdfWriter::text(['DOCUMENTO DEMO '.$marker]));
    }

    /** Campos de recepción; con $for se usa el folio de formato de ese estudio (queda por asociar). */
    protected function intakeFields(?UploadedFile $file = null, string $type = 'result', ?Study $for = null, array $overrides = []): array
    {
        static $sequence = 0;
        $sequence++;

        return array_merge([
            $type === Document::TYPE_PRESCRIPTION ? 'prescription' : 'file' => $file ?? $this->pdfUpload(uniqid()),
            'form_folio' => $for?->external_form_folio ?? sprintf('F-NEW-%04d', $sequence),
            'eira_id' => 'PELU900101',
            'confirm' => '1',
        ], $overrides);
    }

    protected function upload(User $operator, ?UploadedFile $file = null, string $type = 'result', ?Study $for = null, array $overrides = []): Document
    {
        $known = Document::pluck('id');

        $this->actingAs($operator)
            ->post(route('staff.documents.store'), $this->intakeFields($file, $type, $for, $overrides))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Document::whereNotIn('id', $known)->firstOrFail();
    }

    protected function readyDocument(Study $study, ?User $operator = null, ?UploadedFile $file = null, bool $newVersion = false): Document
    {
        $operator ??= $this->operator();
        $document = $this->upload($operator, $file, for: $study);

        $this->actingAs($operator)->post(route('staff.documents.associate', $document), [
            'study_id' => $study->id,
            'confirm' => '1',
            'confirm_new_version' => $newVersion ? '1' : '0',
        ])->assertSessionHasNoErrors();

        $this->actingAs($operator)->post(route('staff.documents.review', $document), [
            'check_legible' => '1',
            'check_matches_study' => '1',
            'check_origin_authorization' => '1',
        ])->assertSessionHasNoErrors();

        return $document->refresh();
    }

    protected function publishedDocument(Study $study, ?UploadedFile $file = null, bool $newVersion = false): Document
    {
        $document = $this->readyDocument($study, null, $file, $newVersion);

        $this->actingAs($this->publisher())
            ->post(route('staff.documents.publish', $document), ['confirm' => '1'])
            ->assertSessionHasNoErrors();

        return $document->refresh();
    }

    protected function codeFor(Study $study, string $channel = 'email'): string
    {
        return app(AccessCodes::class)->issue($study, $channel)[1];
    }

    protected function redeem(Study $study, string $code)
    {
        return $this->asPatient()->post(route('portal.redeem'), ['folio' => $study->external_form_folio, 'code' => $code]);
    }
}
