<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePatientStudySession;
use App\Models\Document;
use App\Models\Study;
use App\Services\Access\AccessCodes;
use App\Services\Audit;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PortalController extends Controller
{
    public const NEUTRAL_ERROR = 'No pudimos validar tus datos o tu documento aún no está disponible. Revisa tu folio y tu código, o solicita apoyo en tu unidad EIRA.';

    private const THROTTLED = 'Demasiados intentos. Espera unos minutos antes de volver a intentar.';

    public function show(Request $request): View|RedirectResponse
    {
        return view('portal.access');
    }

    public function redeem(Request $request, AccessCodes $codes): RedirectResponse
    {
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:20'],
        ], [], ['folio' => 'folio', 'code' => 'código']);

        $folio = Study::normalizeFolio($data['folio']);
        $ipKey = 'portal-redeem:ip:'.$request->ip();
        $folioKey = 'portal-redeem:ref:'.hash('sha256', $folio);

        if (RateLimiter::tooManyAttempts($ipKey, 20) || RateLimiter::tooManyAttempts($folioKey, 10)) {
            return back()->withErrors(['access' => self::THROTTLED])->onlyInput('folio');
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($folioKey, 900);

        $study = $codes->redeem($folio, $data['code']);

        if (! $study) {
            return back()->withErrors(['access' => self::NEUTRAL_ERROR])->onlyInput('folio');
        }

        $request->session()->regenerate();
        $request->session()->put(EnsurePatientStudySession::SESSION_KEY, [
            'study_id' => $study->id,
            'expires_at' => now()->addMinutes(config('eira.patient_session_minutes'))->timestamp,
        ]);

        return redirect()->route('portal.study');
    }

    public function resend(Request $request, NotificationDispatcher $notifications): RedirectResponse
    {
        $data = $request->validate(['folio' => ['required', 'string', 'max:40']]);

        $folio = Study::normalizeFolio($data['folio']);
        $ipKey = 'portal-resend:ip:'.$request->ip();
        $folioKey = 'portal-resend:ref:'.hash('sha256', $folio);

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($folioKey, 3)) {
            return back()->withErrors(['access' => self::THROTTLED])->onlyInput('folio');
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($folioKey, 900);

        Study::where('external_form_folio', $folio)->get()->each(fn (Study $study) => $notifications->resendCode($study));

        return back()
            ->with('status', 'Si el folio es válido y tu documento está disponible, enviamos un código nuevo al contacto registrado en tu unidad EIRA.')
            ->onlyInput('folio');
    }

    public function study(Request $request): View
    {
        $study = Study::findOrFail($request->attributes->get('patient_study_id'));

        return view('portal.study', [
            'study' => $study,
            'documents' => $study->documents()->where('status', Document::STATUS_PUBLISHED)->orderBy('type')->get(),
            'expiresAt' => $request->session()->get(EnsurePatientStudySession::SESSION_KEY)['expires_at'],
        ]);
    }

    public function document(Request $request, string $document, string $mode): Response
    {
        $studyId = $request->attributes->get('patient_study_id');

        $document = Document::whereKey($document)
            ->where('study_id', $studyId)
            ->where('status', Document::STATUS_PUBLISHED)
            ->first();

        $disk = Storage::disk(config('eira.documents_disk'));

        if (! $document || ! $document->delivery_path || ! $disk->exists($document->delivery_path)) {
            Audit::record('document.access_denied', 'denied', $studyId, null, [], 'patient');
            abort(404);
        }

        $download = $mode === 'descargar';
        Audit::record($download ? 'document.downloaded' : 'document.viewed', 'ok', $studyId, $document->id, [], 'patient');

        return $disk->response(
            $document->delivery_path,
            'EIRA-'.($document->type === Document::TYPE_PRESCRIPTION ? 'receta' : 'resultado').'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $download ? 'attachment' : 'inline',
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsurePatientStudySession::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->route('portal.show')->with('status', 'Cerraste tu consulta de forma segura.');
    }
}
