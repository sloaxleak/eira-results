<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Study;
use App\Services\Notifications\DemoOutbox;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $byStatus = Document::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $consultations = AuditEvent::whereIn('action', ['document.viewed', 'document.downloaded'])
            ->selectRaw('study_id, action, count(*) as total, max(created_at) as last_at')
            ->groupBy('study_id', 'action')
            ->get()
            ->groupBy('study_id');

        return view('staff.dashboard', [
            'byStatus' => $byStatus,
            'consultedStudies' => $consultations->count(),
            'consultations' => $consultations,
            'studies' => Study::has('documents')
                ->with(['documents' => fn ($q) => $q->orderByDesc('created_at'), 'notifications', 'contacts'])
                ->latest()
                ->limit(50)
                ->get(),
            'events' => AuditEvent::with(['actor', 'study'])->latest('id')->limit(12)->get(),
        ]);
    }

    public function outbox(DemoOutbox $outbox): View
    {
        abort_unless($outbox->enabled(), 404);

        return view('staff.outbox', ['messages' => $outbox->all()]);
    }

    /** Vista previa del correo de aviso con un código de ejemplo (no válido). */
    public function emailPreview(): Response
    {
        return response(NotificationDispatcher::emailHtml('EJEMPL'))
            ->header('Cache-Control', 'private, no-store');
    }
}
