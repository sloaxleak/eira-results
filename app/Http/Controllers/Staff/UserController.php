<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Captura el nombre.',
        'email.required' => 'Captura el correo.',
        'email.email' => 'El correo no es válido.',
        'email.unique' => 'Ya existe una cuenta con ese correo.',
        'role.in' => 'Selecciona un rol válido.',
        'password.required' => 'Captura una contraseña.',
        'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
    ];

    public function index(): View
    {
        $this->authorizeManage();

        $lastLogins = AuditEvent::where('action', 'staff.login')->where('result', 'ok')
            ->selectRaw('actor_id, max(created_at) as last_at')
            ->groupBy('actor_id')
            ->pluck('last_at', 'actor_id');

        return view('staff.users.index', [
            'users' => User::orderByRaw('disabled_at is not null')->orderBy('name')->get(),
            'lastLogins' => $lastLogins,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('staff.users.form', ['user' => new User(['role' => User::ROLE_OPERATOR])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], self::MESSAGES);

        $user = User::create(['email' => mb_strtolower($data['email'])] + $data);
        Audit::record('user.created', meta: ['user_id' => $user->id, 'role' => $user->role]);

        return redirect()->route('staff.users.index')->with('status', "Cuenta creada para {$user->name}.");
    }

    public function edit(User $user): View
    {
        $this->authorizeManage();

        return view('staff.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['nullable', 'confirmed', Password::min(12)],
        ], self::MESSAGES);

        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return back()->withErrors(['role' => 'No puedes cambiar tu propio rol. Pide a otra cuenta de administración que lo haga.'])->withInput();
        }

        $previousRole = $user->role;
        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
        ]);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            Audit::record('user.updated', meta: array_filter([
                'user_id' => $user->id,
                'fields' => implode(',', $changed),
                'role' => in_array('role', $changed, true) ? "{$previousRole}→{$user->role}" : null,
            ]));
        }

        if (! empty($data['password'])) {
            $user->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
            Audit::record('user.password_reset', meta: ['user_id' => $user->id]);
        }

        return redirect()->route('staff.users.index')->with('status', "Cuenta de {$user->name} actualizada.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage();

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta.']);
        }

        $disable = $user->disabled_at === null;
        $user->forceFill(['disabled_at' => $disable ? now() : null, 'remember_token' => null])->save();
        Audit::record($disable ? 'user.disabled' : 'user.enabled', meta: ['user_id' => $user->id]);

        return back()->with('status', $disable
            ? "Cuenta de {$user->name} desactivada. Su sesión deja de funcionar en la siguiente solicitud."
            : "Cuenta de {$user->name} reactivada.");
    }

    private function authorizeManage(): void
    {
        if (! Gate::allows('users.manage')) {
            Audit::record('user.manage_denied', 'denied');
            abort(403);
        }
    }
}
