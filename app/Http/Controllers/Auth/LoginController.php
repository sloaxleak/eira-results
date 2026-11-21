<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'staff-login:'.hash('sha256', mb_strtolower($credentials['email']).'|'.$request->ip());

        $attempted = ['email' => mb_substr(mb_strtolower($credentials['email']), 0, 120)];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            Audit::record('staff.login', 'throttled', meta: $attempted);

            return back()->withErrors(['email' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'])->onlyInput('email');
        }

        if (! Auth::attemptWhen($credentials, fn ($user) => $user->disabled_at === null)) {
            RateLimiter::hit($key, 60);
            Audit::record('staff.login', 'denied', meta: $attempted);

            return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::record('staff.login');

        return redirect()->intended(route('staff.dashboard'));
    }

    public static function quickLoginEnabled(): bool
    {
        return config('eira.demo_quick_login') && ! app()->isProduction();
    }

    /** SOLO DEMO: cambia a una de las cuentas DEMO sembradas sin contraseña; cada acción sigue a nombre de esa cuenta. */
    public function quick(Request $request, string $role): RedirectResponse
    {
        abort_unless(self::quickLoginEnabled(), 404);

        $email = config('eira.demo_accounts')[$role] ?? abort(404);
        $user = User::where('email', $email)->whereNull('disabled_at')->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'La cuenta DEMO no existe o está desactivada. Ejecuta php artisan db:seed.']);
        }

        if (Auth::check()) {
            Audit::record('staff.logout', meta: ['method' => 'demo_switch']);
            Auth::logout();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        Audit::record('staff.login', meta: ['method' => 'demo_quick']);

        $target = (string) $request->input('redirect');
        $path = parse_url($target, PHP_URL_PATH);
        $sameHost = parse_url($target, PHP_URL_HOST) === $request->getHost();

        return $sameHost && is_string($path) && preg_match('#^/staff(/[\w\-/]*)?$#', $path)
            ? redirect($path)
            : redirect()->route('staff.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Audit::record('staff.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
