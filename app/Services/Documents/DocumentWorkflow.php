<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Study;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentWorkflow
{
    public function __construct(
        private ImageToPdf $images,
        private NotificationDispatcher $notifications,
    ) {}

    public function activeSiblings(Document $document, Study $study)
    {
        return Document::where('study_id', $study->id)
            ->where('type', $document->type)
            ->whereKeyNot($document->id)
            ->whereIn('status', Document::ACTIVE_STATUSES)
            ->orderByDesc('version')
            ->get();
    }

    public function associate(Document $document, Study $study, User $user, bool $confirmNewVersion): void
    {
        $this->requireStatus($document, [Document::STATUS_PENDING_ASSOCIATION]);

        $siblings = $this->activeSiblings($document, $study);

        if ($siblings->isNotEmpty() && ! $confirmNewVersion) {
            throw new WorkflowException('duplicate_requires_confirmation',
                'El estudio ya tiene un documento activo de este tipo. Confirma que se trata de una corrección para crear una nueva versión.');
        }

        $lastVersion = (int) Document::where('study_id', $study->id)->where('type', $document->type)->max('version');

        $document->update([
            'study_id' => $study->id,
            'status' => Document::STATUS_PENDING_REVIEW,
            'version' => $lastVersion + 1,
            'supersedes_id' => $siblings->first()?->id,
            'associated_by' => $user->id,
            'associated_at' => now(),
        ]);

        Audit::record('document.associated', 'ok', $study->id, $document->id, ['version' => $document->version]);
    }

    public function review(Document $document, User $user): void
    {
        $this->requireStatus($document, [Document::STATUS_PENDING_REVIEW]);

        $disk = Storage::disk(config('eira.documents_disk'));
        $deliveryPath = $document->original_path;
        $deliverySha = $document->sha256;

        if ($document->isImage()) {
            $pdf = $this->images->convert($disk->get($document->original_path));
            $deliveryPath = 'delivery/'.$document->id.'.pdf';
            $deliverySha = hash('sha256', $pdf);
            $disk->put($deliveryPath, $pdf);
        }

        $document->update([
            'status' => Document::STATUS_READY,
            'check_legible' => true,
            'check_matches_study' => true,
            'check_origin_authorization' => true,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'delivery_path' => $deliveryPath,
            'delivery_sha256' => $deliverySha,
        ]);

        Audit::record('document.reviewed', 'ok', $document->study_id, $document->id);
    }

    public function reject(Document $document, User $user, string $reason): void
    {
        $this->requireStatus($document, [
            Document::STATUS_PENDING_ASSOCIATION,
            Document::STATUS_PENDING_REVIEW,
            Document::STATUS_READY,
        ]);

        $document->update([
            'status' => Document::STATUS_REJECTED,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        Audit::record('document.rejected', 'ok', $document->study_id, $document->id, ['reason' => $reason]);
    }

    public function publish(Document $document, User $user): void
    {
        $this->requireStatus($document, [Document::STATUS_READY]);

        if (! $document->study_id || ! $document->reviewed_at || ! $document->reviewed_by
            || ! $document->check_legible || ! $document->check_matches_study || ! $document->check_origin_authorization) {
            throw new WorkflowException('preconditions_not_met', 'El documento no cumple las precondiciones de asociación y revisión.');
        }

        $disk = Storage::disk(config('eira.documents_disk'));

        if (! $document->delivery_path || ! $disk->exists($document->delivery_path)
            || ! hash_equals((string) $document->delivery_sha256, hash('sha256', $disk->get($document->delivery_path)))) {
            Audit::record('document.publish_blocked', 'integrity_failed', $document->study_id, $document->id);

            throw new WorkflowException('integrity_failed', 'La verificación de integridad del archivo falló. No se publicó.');
        }

        $published = DB::transaction(function () use ($document, $user) {
            $updated = Document::whereKey($document->id)
                ->where('status', Document::STATUS_READY)
                ->update([
                    'status' => Document::STATUS_PUBLISHED,
                    'published_by' => $user->id,
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return false;
            }

            Document::where('study_id', $document->study_id)
                ->where('type', $document->type)
                ->whereKeyNot($document->id)
                ->where('status', Document::STATUS_PUBLISHED)
                ->update([
                    'status' => Document::STATUS_REVOKED,
                    'revoked_by' => $user->id,
                    'revoked_at' => now(),
                    'revocation_reason' => 'superseded',
                    'updated_at' => now(),
                ]);

            return true;
        });

        if (! $published) {
            throw new WorkflowException('already_processed', 'El documento ya fue procesado por otra acción.');
        }

        $document->refresh();
        Audit::record('document.published', 'ok', $document->study_id, $document->id, ['version' => $document->version]);

        try {
            $this->notifications->notifyPublication($document);
        } catch (Throwable $e) {
            Audit::record('notification.dispatch', 'error', $document->study_id, $document->id, ['error' => class_basename($e)]);
        }
    }

    public function revoke(Document $document, User $user, string $reason): void
    {
        $this->requireStatus($document, [Document::STATUS_PUBLISHED]);

        $document->update([
            'status' => Document::STATUS_REVOKED,
            'revoked_by' => $user->id,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        Audit::record('document.revoked', 'ok', $document->study_id, $document->id, ['reason' => $reason]);
    }

    private function requireStatus(Document $document, array $statuses): void
    {
        if (! in_array($document->status, $statuses, true)) {
            throw new WorkflowException('invalid_state', 'La acción no es válida para el estado actual del documento.');
        }
    }
}
