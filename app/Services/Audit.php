<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;

class Audit
{
    /**
     * Registra un evento operativo. $meta solo admite datos técnicos no clínicos
     * (canal, motivo codificado, versión); nunca OTP, tokens, cuerpos de mensaje ni diagnósticos.
     */
    public static function record(
        string $action,
        string $result = 'ok',
        ?string $studyId = null,
        ?string $documentId = null,
        array $meta = [],
        ?string $actorType = null,
    ): AuditEvent {
        $userId = Auth::id();

        return AuditEvent::create([
            'actor_type' => $actorType ?? ($userId ? 'staff' : 'system'),
            'actor_id' => $actorType === 'patient' ? null : $userId,
            'study_id' => $studyId,
            'document_id' => $documentId,
            'action' => $action,
            'result' => $result,
            'meta' => $meta ?: null,
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }
}
