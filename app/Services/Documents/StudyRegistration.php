<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Study;
use App\Models\StudyContact;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Registra el estudio a partir de los datos transcritos del formato al recibir el documento.
 * Si ya existe un estudio con el mismo folio de formato no asocia nada: el documento queda
 * pendiente y la decisión (duplicado o corrección) es humana.
 */
class StudyRegistration
{
    public function __construct(private DocumentWorkflow $workflow) {}

    /** @param array{form_folio: string, eira_id: string, cytology_number?: ?string, taken_at?: ?string, phone?: ?string, email?: ?string} $data */
    public function register(Document $document, array $data, User $user): ?Study
    {
        if (Study::where('external_form_folio', $data['form_folio'])->exists()) {
            Audit::record('study.form_folio_exists', 'pending', null, $document->id);

            return null;
        }

        $study = DB::transaction(function () use ($data) {
            $patient = Patient::create(['external_eira_id' => $data['eira_id']]);

            $study = Study::create([
                'patient_id' => $patient->id,
                'folio' => Study::generateFolio(),
                'external_form_folio' => $data['form_folio'],
                'external_cytology_number' => empty($data['cytology_number']) ? null : Study::normalizeFolio($data['cytology_number']),
                'taken_at' => $data['taken_at'] ?? null,
            ]);

            foreach ([StudyContact::CHANNEL_SMS => $data['phone'] ?? null, StudyContact::CHANNEL_EMAIL => $data['email'] ?? null] as $channel => $value) {
                if ($value) {
                    StudyContact::create([
                        'study_id' => $study->id,
                        'channel' => $channel,
                        'value' => $value,
                        'verified_at' => null,
                        'source' => 'transcrito_de_formato',
                    ]);
                }
            }

            return $study;
        });

        Audit::record('study.registered', 'ok', $study->id, $document->id);
        $this->workflow->associate($document, $study, $user, false);

        return $study;
    }
}
