<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoQuickLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_quick_login_is_off_by_default(): void
    {
        $this->post(route('login.demo', 'publisher'))->assertNotFound();
        $this->assertGuest();
        $this->get(route('login'))->assertDontSee('Entrar con cuenta DEMO');
    }

    public function test_quick_login_is_never_available_in_production(): void
    {
        config(['eira.demo_quick_login' => true]);
        $this->app['env'] = 'production';

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('login.demo', 'publisher'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_switching_keeps_segregation_and_audit_attribution(): void
    {
        config(['eira.demo_quick_login' => true]);
        $operator = User::where('email', 'operador@eira.example')->sole();
        $publisher = User::where('email', 'publicador@eira.example')->sole();

        $this->get(route('login'))->assertSee('Entrar con cuenta DEMO');
        $this->post(route('login.demo', 'operator'))->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($operator);

        $document = $this->upload($operator);
        $this->post(route('staff.documents.review', $document), [
            'check_legible' => '1', 'check_matches_study' => '1', 'check_origin_authorization' => '1',
        ]);
        $this->post(route('staff.documents.publish', $document), ['confirm' => '1'])->assertForbidden();

        $this->post(route('login.demo', 'publisher'))->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($publisher);
        $this->post(route('staff.documents.publish', $document), ['confirm' => '1'])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame($operator->id, $document->reviewed_by);
        $this->assertSame($publisher->id, $document->published_by);
        $this->assertSame(2, AuditEvent::where('action', 'staff.login')->where('meta->method', 'demo_quick')->count());
    }

    public function test_operator_sees_why_it_cannot_publish_and_can_switch_back_to_the_document(): void
    {
        config(['eira.demo_quick_login' => true]);
        $operator = User::where('email', 'operador@eira.example')->sole();
        $document = $this->readyDocument($this->study(), $operator);
        $url = route('staff.documents.show', $document);

        $this->actingAs($operator)->get($url)->assertOk()
            ->assertSee('no puede publicar', false)
            ->assertSee('Cambiar a Publicadora DEMO para publicar')
            ->assertDontSee(route('staff.documents.publish', $document));

        $this->post(route('login.demo', 'publisher'), ['redirect' => $url])->assertRedirect(parse_url($url, PHP_URL_PATH));
        $this->get($url)->assertOk()->assertSee(route('staff.documents.publish', $document));

        foreach (['https://evil.example/staff', '//evil.example/staff', 'http://localhost/consulta'] as $bad) {
            $this->post(route('login.demo', 'operator'), ['redirect' => $bad])->assertRedirect(route('staff.dashboard'));
        }
    }

    public function test_disabled_demo_account_cannot_be_used(): void
    {
        config(['eira.demo_quick_login' => true]);
        User::where('email', 'publicador@eira.example')->update(['disabled_at' => now()]);

        $this->post(route('login.demo', 'publisher'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
