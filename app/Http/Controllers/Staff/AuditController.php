<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Study;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditController extends Controller
{
    /** categoría => [etiqueta, prefijos o acciones exactas] */
    public const CATEGORIES = [
        'staff' => ['Accesos de personal', ['staff.']],
        'users' => ['Gestión de usuarios', ['user.']],
        'patient' => ['Accesos de pacientes', ['access.', 'document.viewed', 'document.downloaded', 'document.access_denied']],
        'documents' => ['Documentos', ['document.']],
        'notifications' => ['Avisos', ['notification.']],
        'studies' => ['Estudios y contactos', ['study.', 'contact.']],
    ];

    /** filtro => resultados incluidos */
    public const RESULTS = [
        'ok' => ['Correcto', ['ok', 'accepted', 'delivered']],
        'denied' => ['Denegado', ['denied', 'rejected', 'throttled']],
        'failed' => ['Error o fallo', ['failed', 'error', 'integrity_failed', 'disabled', 'unknown']],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))],
            'result' => ['nullable', Rule::in(array_keys(self::RESULTS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'folio' => ['nullable', 'string', 'max:40'],
        ]);

        $tz = config('eira.display_timezone');
        $query = AuditEvent::with(['actor', 'study'])->latest('id');

        if ($category = $filters['category'] ?? null) {
            $query->where(function ($q) use ($category) {
                foreach (self::CATEGORIES[$category][1] as $pattern) {
                    str_ends_with($pattern, '.') ? $q->orWhere('action', 'like', $pattern.'%') : $q->orWhere('action', $pattern);
                }
            });
        }

        if ($result = $filters['result'] ?? null) {
            $query->whereIn('result', self::RESULTS[$result][1]);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], $tz)->startOfDay()->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], $tz)->endOfDay()->utc());
        }

        if (! empty($filters['folio'])) {
            $folio = Study::normalizeFolio($filters['folio']);
            $query->whereIn('study_id', Study::where('folio', $folio)->orWhere('external_form_folio', $folio)->pluck('id'));
        }

        return view('staff.audit', [
            'events' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'notificationSummary' => DeliveryNotification::selectRaw('channel, status, count(*) as total')
                ->groupBy('channel', 'status')
                ->get()
                ->groupBy('channel'),
        ]);
    }
}
