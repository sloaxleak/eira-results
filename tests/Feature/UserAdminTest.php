<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_only_admin_can_manage_users_even_by_direct_route(): void
    {
        $target = $this->operator();

        foreach ([$this->operator(), $this->publisher()] as $user) {
            $this->actingAs($user)->get(route('staff.users.index'))->assertForbidden();
            $this->actingAs($user)->post(route('staff.users.store'), [
                'name' => 'X', 'email' => 'x@eira.example', 'role' => 'publisher',
                'password' => 'una-clave-larga-1', 'password_confirmation' => 'una-clave-larga-1',
            ])->assertForbidden();
            $this->actingAs($user)->put(route('staff.users.update', $target), ['name' => 'X', 'email' => $target->email, 'role' => 'admin'])->assertForbidden();
            $this->actingAs($user)->post(route('staff.users.toggle', $target))->assertForbidden();
        }

        $this->assertSame(User::ROLE_OPERATOR, $target->refresh()->role);
        $this->assertSame(0, User::where('email', 'x@eira.example')->count());
        $this->actingAs($this->operator())->get(route('staff.dashboard'))->assertDontSee(route('staff.users.index'));
    }

    public function test_admin_creates_an_account_that_can_log_in_with_its_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('staff.users.index'))->assertOk()->assertSee($admin->email);

        $this->actingAs($admin)->post(route('staff.users.store'), [
            'name' => 'Nueva Publicadora', 'email' => 'Nueva@EIRA.example', 'role' => User::ROLE_PUBLISHER,
            'password' => 'una-clave-larga-1', 'password_confirmation' => 'una-clave-larga-1',
        ])->assertRedirect(route('staff.users.index'));

        $user = User::where('email', 'nueva@eira.example')->sole();
        $this->assertSame(User::ROLE_PUBLISHER, $user->role);
        $this->assertTrue(Hash::check('una-clave-larga-1', $user->password));

        $event = AuditEvent::where('action', 'user.created')->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertStringNotContainsString('una-clave-larga-1', AuditEvent::all()->toJson());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post(route('login.attempt'), ['email' => 'nueva@eira.example', 'password' => 'una-clave-larga-1'])
            ->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('staff.users.index'))->assertForbidden();
    }

    public function test_validation_rejects_short_passwords_duplicates_and_unknown_roles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('staff.users.store'), [
            'name' => 'A', 'email' => $admin->email, 'role' => 'superadmin',
            'password' => 'corta', 'password_confirmation' => 'distinta',
        ])->assertSessionHasErrors(['email', 'role', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_admin_cannot_change_own_role_or_disable_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('staff.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => User::ROLE_PUBLISHER,
        ])->assertSessionHasErrors('role');
        $this->actingAs($admin)->post(route('staff.users.toggle', $admin))->assertSessionHasErrors('user');

        $admin->refresh();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNull($admin->disabled_at);
    }

    public function test_role_change_and_password_reset_are_audited(): void
    {
        $admin = $this->admin();
        $user = $this->operator();

        $this->actingAs($admin)->put(route('staff.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => User::ROLE_PUBLISHER,
            'password' => 'otra-clave-larga-2', 'password_confirmation' => 'otra-clave-larga-2',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(User::ROLE_PUBLISHER, $user->role);
        $this->assertTrue(Hash::check('otra-clave-larga-2', $user->password));
        $this->assertSame('operator→publisher', AuditEvent::where('action', 'user.updated')->sole()->meta['role']);
        $this->assertTrue(AuditEvent::where('action', 'user.password_reset')->exists());
    }

    public function test_disabled_account_cannot_log_in_and_its_open_session_stops_working(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => User::ROLE_OPERATOR, 'email' => 'baja@eira.example']);

        $this->actingAs($admin)->post(route('staff.users.toggle', $user))->assertSessionHasNoErrors();
        $this->assertNotNull($user->refresh()->disabled_at);

        $this->actingAs($user)->get(route('staff.dashboard'))->assertForbidden();
        $this->actingAs($user)->post(route('staff.documents.store'), $this->intakeFields())->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->post(route('login.attempt'), ['email' => 'baja@eira.example', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->post(route('staff.users.toggle', $user));
        $this->assertNull($user->refresh()->disabled_at);
        $this->assertTrue(AuditEvent::where('action', 'user.enabled')->exists());
    }
}
