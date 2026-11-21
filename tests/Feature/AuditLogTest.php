<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logins_document_access_and_notifications_appear_in_the_audit_log(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR, 'email' => 'op@eira.example']);
        $this->post(route('login.attempt'), ['email' => 'op@eira.example', 'password' => 'incorrecta']);
        $this->post(route('login.attempt'), ['email' => 'op@eira.example', 'password' => 'password'])->assertRedirect();

        $study = $this->study([['email', 'paciente.demo@eira.example', true]]);
        $document = $this->publishedDocument($study);
        $code = $this->codeFor($study);
        $this->redeem($study, $code);
        $this->get(route('portal.document', [$document->id, 'descargar']))->assertOk();

        $this->assertSame('127.0.0.1', AuditEvent::where('action', 'staff.login')->where('result', 'ok')->value('ip'));
        $this->assertSame('op@eira.example', AuditEvent::where('action', 'staff.login')->where('result', 'denied')->first()->meta['email']);

        $page = $this->actingAs($operator)->get(route('staff.audit'))->assertOk()
            ->assertSee('Inicio de sesión de personal')
            ->assertSee('Descarga (paciente)')
            ->assertSee('Código canjeado')
            ->assertSee('Intento de aviso')
            ->assertSee($study->folio)
            ->assertDontSee('paciente.demo@eira.example')
            ->assertDontSee($code);

        $this->actingAs($operator)->get(route('staff.audit', ['category' => 'staff']))->assertOk()
            ->assertSee('Inicio de sesión de personal')
            ->assertDontSee('Descarga (paciente)');

        $this->actingAs($operator)->get(route('staff.audit', ['category' => 'staff', 'result' => 'denied']))->assertOk()
            ->assertSee('op@eira.example');

        $this->actingAs($operator)->get(route('staff.audit', ['folio' => mb_strtolower($study->external_form_folio)]))->assertOk()
            ->assertSee('Descarga (paciente)')
            ->assertDontSee('Inicio de sesión de personal');
    }

    public function test_audit_log_requires_staff_session(): void
    {
        $this->get(route('staff.audit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'none']))->get(route('staff.audit'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('staff.audit'))->assertOk();
    }

    public function test_dashboard_no_longer_shows_the_notifications_counter(): void
    {
        $this->actingAs($this->operator())->get(route('staff.dashboard'))->assertOk()
            ->assertDontSee('Aceptados por transporte / con error')
            ->assertSee('Consultados')
            ->assertSee('Aún no hay estudios');
    }

    public function test_dashboard_lists_only_studies_with_documents_by_form_folio(): void
    {
        $orphan = $this->study();
        $document = $this->upload($this->operator(), overrides: ['form_folio' => '777001']);

        $this->actingAs($this->operator())->get(route('staff.dashboard'))->assertOk()
            ->assertSee('777001')
            ->assertSee($document->study->folio)
            ->assertDontSee($orphan->folio)
            ->assertDontSee('Sin documento');
    }
}
