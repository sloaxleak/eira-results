<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatientStudySession
{
    public const SESSION_KEY = 'patient_access';

    public function handle(Request $request, Closure $next): Response
    {
        $access = $request->session()->get(self::SESSION_KEY);

        if (! is_array($access) || empty($access['study_id']) || ($access['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('portal.show')
                ->withErrors(['access' => 'Tu sesión de consulta terminó. Ingresa de nuevo con tu folio y un código vigente.']);
        }

        $request->attributes->set('patient_study_id', $access['study_id']);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
