<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Models\Study;
use App\Models\StudyContact;
use App\Services\Audit;
use App\Services\Documents\DocumentIntake;
use App\Services\Documents\DocumentWorkflow;
use App\Services\Documents\StudyRegistration;
use App\Services\Documents\WorkflowException;
use App\Services\Notifications\NotificationDispatcher;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(): View
    {
        return view('staff.intake', [
            'documents' => Document::with(['study', 'uploader'])
                ->whereIn('status', [Document::STATUS_PENDING_ASSOCIATION, Document::STATUS_PENDING_REVIEW])
                ->latest()
                ->get(),
            'closed' => Document::with('study')
                ->whereIn('status', [Document::STATUS_REJECTED, Document::STATUS_REVOKED])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function publication(): View
    {
        return view('staff.publication', [
            'ready' => Document::with(['study', 'reviewer'])->where('status', Document::STATUS_READY)->oldest('reviewed_at')->get(),
            'published' => Document::with(['study', 'publisher', 'notifications'])
                ->where('status', Document::STATUS_PUBLISHED)
                ->latest('published_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(Request $request, DocumentIntake $intake, StudyRegistration $registration, DocumentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAction('documents.intake', 'document.upload_denied');

        $phone = preg_replace('/[\s\-().]/', '', (string) $request->input('phone'));
        $request->merge([
            'form_folio' => Study::normalizeFolio((string) $request->input('form_folio')),
            'eira_id' => Study::normalizeFolio((string) $request->input('eira_id')),
            'phone' => $phone === '' ? null : (preg_match('/^\d{10}$/', $phone) ? '+52'.$phone : $phone),
            'email' => trim((string) $request->input('email')) ?: null,
        ]);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('eira.upload.max_kb')],
            'prescription' => ['nullable', 'file', 'max:'.config('eira.upload.max_kb')],
            'form_folio' => ['required', 'string', 'max:40'],
            'eira_id' => ['required', 'regex:/^[A-ZÑ]{4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/u'],
            'cytology_number' => ['nullable', 'string', 'max:40'],
            'taken_at' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'regex:/^\+\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'confirm' => ['accepted'],
        ], [
            'file.required' => 'Selecciona el archivo del resultado.',
            'file.file' => 'El resultado no se pudo recibir.',
            'file.uploaded' => 'El resultado no se pudo recibir. Verifica que no exceda el tamaño permitido.',
            'file.max' => 'El resultado excede el tamaño máximo permitido.',
            'prescription.file' => 'La receta no se pudo recibir.',
            'prescription.uploaded' => 'La receta no se pudo recibir. Verifica que no exceda el tamaño permitido.',
            'prescription.max' => 'La receta excede el tamaño máximo permitido.',
            'form_folio.required' => 'Captura el No. de folio del formato.',
            'eira_id.required' => 'Captura el ID EIRA del formato.',
            'eira_id.regex' => 'El ID EIRA debe tener 4 letras y la fecha de nacimiento AAMMDD (ej. JACA990214).',
            'taken_at.before_or_equal' => 'La fecha de toma no puede ser futura.',
            'phone.regex' => 'El teléfono debe tener 10 dígitos.',
            'email.email' => 'El correo no es válido.',
            'confirm.accepted' => 'Confirma que transcribiste los datos tal como aparecen en el formato.',
        ]);

        $user = $request->user();
        $resultFile = $intake->inspect($request->file('file'), 'file');
        $prescriptionFile = $request->hasFile('prescription') ? $intake->inspect($request->file('prescription'), 'prescription') : null;

        if ($prescriptionFile && $prescriptionFile['sha256'] === $resultFile['sha256']) {
            $intake->fail('prescription', 'same_as_result', 'La receta es el mismo archivo que el resultado.');
        }

        $document = $intake->persist($resultFile, Document::TYPE_RESULT, $user);
        $prescription = $prescriptionFile ? $intake->persist($prescriptionFile, Document::TYPE_PRESCRIPTION, $user) : null;
        $study = $registration->register($document, $data, $user);
        $withPrescription = $prescription ? ' La receta se recibió por separado y requiere su propia revisión y publicación.' : '';

        if (! $study) {
            return redirect()->route('staff.documents.show', [$document, 'folio' => $data['form_folio']])
                ->with('status', 'Ya existe un estudio con ese folio de formato. El documento quedó por asociar: verifica si es el mismo estudio o una corrección.'.$withPrescription);
        }

        if ($prescription) {
            $workflow->associate($prescription, $study, $user, false);
        }

        return redirect()->route('staff.documents.show', $document)
            ->with('status', "Recibido y registrado con folio interno {$study->folio}. Continúa con la revisión.".$withPrescription);
    }

    public function verifyContact(StudyContact $contact, NotificationDispatcher $notifications): RedirectResponse
    {
        $this->authorizeAction('documents.intake', 'contact.verify_denied');

        if (! $contact->verified_at) {
            $contact->update(['verified_at' => now(), 'source' => 'confirmado_por_personal']);
            Audit::record('contact.verified', 'ok', $contact->study_id, null, ['channel' => $contact->channel]);
        }

        $published = Document::where('study_id', $contact->study_id)->where('status', Document::STATUS_PUBLISHED)->get();

        if ($published->isEmpty()) {
            return back()->with('status', 'Contacto confirmado. Recibirá el código cuando se publique el documento.');
        }

        // El documento ya estaba publicado: avisar ahora. La idempotencia por documento + contacto evita reenviar a quien ya tenía aviso.
        $published->each(fn (Document $document) => $notifications->notifyPublication($document));

        return back()->with('status', 'Contacto confirmado. Como el documento ya estaba publicado, se envió el aviso a este contacto; revisa su estado.');
    }

    public function show(Request $request, Document $document, DocumentWorkflow $workflow): View
    {
        $query = trim((string) $request->query('folio'));
        $candidates = collect();

        if ($query !== '' && $document->status === Document::STATUS_PENDING_ASSOCIATION) {
            $normalized = Study::normalizeFolio($query);
            $candidates = Study::where('folio', $normalized)
                ->orWhere('external_form_folio', $normalized)
                ->orWhere('external_cytology_number', $normalized)
                ->limit(5)
                ->get()
                ->each(fn (Study $study) => $study->setAttribute('active_siblings', $workflow->activeSiblings($document, $study)->count()));
        }

        return view('staff.document', [
            'document' => $document->load(['study.contacts', 'study.patient', 'uploader', 'associator', 'reviewer', 'publisher', 'notifications.contact']),
            'query' => $query,
            'candidates' => $candidates,
            'siblings' => $document->study_id
                ? Document::where('study_id', $document->study_id)->whereKeyNot($document->id)->orderBy('type')->orderByDesc('version')->get()
                : collect(),
            'events' => AuditEvent::with('actor')->where('document_id', $document->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function file(Document $document, string $variant = 'original'): Response
    {
        $this->authorizeAction('documents.view-file', 'document.file_denied', $document);

        $path = $variant === 'entrega' ? $document->delivery_path : $document->original_path;
        $disk = Storage::disk(config('eira.documents_disk'));

        abort_unless($path && $disk->exists($path), 404);

        Audit::record('document.staff_viewed', 'ok', $document->study_id, $document->id, ['variant' => $variant]);

        return $disk->response($path, 'documento-'.$document->shortId(), [
            'Content-Type' => $variant === 'entrega' ? 'application/pdf' : $document->original_mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function associate(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAction('documents.intake', 'document.associate_denied', $document);

        $data = $request->validate([
            'study_id' => ['required', 'uuid', 'exists:studies,id'],
            'confirm' => ['accepted'],
            'confirm_new_version' => ['sometimes', 'boolean'],
        ], ['confirm.accepted' => 'Debes confirmar que verificaste la correspondencia con el estudio.']);

        return $this->run($document, fn () => $workflow->associate(
            $document,
            Study::findOrFail($data['study_id']),
            $request->user(),
            $request->boolean('confirm_new_version'),
        ), 'Documento asociado. Continúa con la revisión.');
    }

    public function review(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAction('documents.intake', 'document.review_denied', $document);

        $request->validate([
            'check_legible' => ['accepted'],
            'check_matches_study' => ['accepted'],
            'check_origin_authorization' => ['accepted'],
        ], ['accepted' => 'Debes confirmar los tres puntos de la revisión; si alguno no se cumple, rechaza el documento.']);

        if (! config('eira.publish_on_review')) {
            return $this->run($document, fn () => $workflow->review($document, $request->user()),
                'Revisión registrada. El documento quedó listo; ahora debe publicarlo una cuenta con facultad de publicación (no la de operación).');
        }

        // Modo de un solo paso (decisión temporal de EIRA): confirmar la revisión publica y notifica.
        return $this->run($document, function () use ($request, $document, $workflow) {
            $workflow->review($document, $request->user());

            if ($request->boolean('confirm_contacts')) {
                $document->study->contacts()->whereNull('verified_at')->get()->each(function (StudyContact $contact) {
                    $contact->update(['verified_at' => now(), 'source' => 'confirmado_en_revision']);
                    Audit::record('contact.verified', 'ok', $contact->study_id, null, ['channel' => $contact->channel, 'method' => 'review']);
                });
            }

            $workflow->publish($document->refresh(), $request->user());
        }, 'Revisión confirmada y documento publicado. Se envió el aviso a los contactos confirmados; revisa su estado abajo.');
    }

    public function reject(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        if (! Gate::any(['documents.intake', 'documents.publish'])) {
            $this->deny('document.reject_denied', $document);
        }

        if ($document->status === Document::STATUS_READY) {
            $this->authorizeAction('documents.publish', 'document.reject_denied', $document);
        } else {
            $this->authorizeAction('documents.intake', 'document.reject_denied', $document);
        }

        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Document::REJECTION_REASONS))]]);

        return $this->run($document, fn () => $workflow->reject($document, $request->user(), $data['reason']),
            'Documento rechazado. Queda registrado en la bitácora.');
    }

    public function publish(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAction('documents.publish', 'document.publish_denied', $document);

        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirma la decisión de publicar.']);

        return $this->run($document, fn () => $workflow->publish($document, $request->user()),
            'Documento publicado. Los avisos se gestionan de forma independiente; revisa su estado.');
    }

    public function revoke(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAction('documents.publish', 'document.revoke_denied', $document);

        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Document::REVOCATION_REASONS))]]);

        return $this->run($document, fn () => $workflow->revoke($document, $request->user(), $data['reason']),
            'Publicación revocada. El documento dejó de estar disponible para consulta.');
    }

    public function retryNotification(DeliveryNotification $notification, NotificationDispatcher $notifications): RedirectResponse
    {
        if (! Gate::any(['documents.intake', 'documents.publish'])) {
            $this->deny('notification.retry_denied');
        }

        return back()->with('status', $notifications->retry($notification)
            ? 'Reintento registrado. Revisa el estado del aviso.'
            : 'Este aviso no admite reintento.');
    }

    private function run(Document $document, Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (WorkflowException $e) {
            return redirect()->route('staff.documents.show', $document)->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('staff.documents.show', $document)->with('status', $success);
    }

    private function authorizeAction(string $ability, string $auditAction, ?Document $document = null): void
    {
        if (! Gate::allows($ability)) {
            $this->deny($auditAction, $document);
        }
    }

    private function deny(string $auditAction, ?Document $document = null): never
    {
        Audit::record($auditAction, 'denied', $document?->study_id, $document?->id);
        abort(403);
    }
}
