<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DeliveryNotification;
use App\Models\Document;
use App\Services\Documents\DocumentWorkflow;
use App\Services\Notifications\ClickSendSmsSender;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClickSendSmsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://rest.clicksend.com/v3/sms/send';

    private function queued(string $status = 'SUCCESS'): array
    {
        return [
            'http_code' => 200,
            'response_code' => 'SUCCESS',
            'data' => ['messages' => [['to' => '+525512345678', 'message_id' => 'CS-MSG-123', 'status' => $status]]],
        ];
    }

    public function test_successful_queue_is_recorded_as_accepted_not_delivered(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->queued())]);

        $result = (new ClickSendSmsSender('usuario', 'clave', 'EIRA'))->send('+525512345678', 'Texto', 'k1');

        $this->assertSame('accepted', $result->status);
        $this->assertSame('CS-MSG-123', $result->providerId);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::ENDPOINT
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('usuario:clave'))
                && $request['messages'][0]['to'] === '+525512345678'
                && $request['messages'][0]['body'] === 'Texto'
                && $request['messages'][0]['from'] === 'EIRA';
        });
    }

    public function test_provider_rejections_and_errors_become_sanitized_failures(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push($this->queued('INVALID_RECIPIENT'))
            ->push(['response_code' => 'INVALID_CREDENTIALS'], 401)
            ->push($this->queued('INSUFFICIENT_CREDIT')),
        ]);
        $sender = new ClickSendSmsSender('usuario', 'clave');

        $this->assertSame('clicksend_invalid_recipient', $sender->send('+525500000001', 'x', 'k')->error);
        $this->assertSame('clicksend_http_401', $sender->send('+525500000001', 'x', 'k')->error);
        $this->assertSame('clicksend_insufficient_credit', $sender->send('+525500000001', 'x', 'k')->error);
    }

    public function test_missing_credentials_fail_without_calling_the_provider(): void
    {
        Http::fake();

        $result = (new ClickSendSmsSender(null, null))->send('+525512345678', 'x', 'k');

        $this->assertSame('failed', $result->status);
        $this->assertSame('clicksend_not_configured', $result->error);
        Http::assertNothingSent();
    }

    public function test_publication_sends_through_clicksend_and_a_failure_never_blocks_the_document(): void
    {
        config(['eira.sms_driver' => 'clicksend', 'eira.clicksend' => ['username' => 'usuario', 'key' => 'clave-secreta', 'from' => null]]);
        foreach ([SmsSender::class, NotificationDispatcher::class, DocumentWorkflow::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        Http::fake([self::ENDPOINT => Http::sequence()->push($this->queued())->push([], 500)]);

        $ok = $this->study([['sms', '+525512345678', true]]);
        $this->publishedDocument($ok);
        $failing = $this->study([['sms', '+525587654321', true]]);
        $document = $this->publishedDocument($failing);

        $accepted = DeliveryNotification::where('study_id', $ok->id)->sole();
        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('CS-MSG-123', $accepted->provider_message_id);

        $this->assertSame(Document::STATUS_PUBLISHED, $document->status);
        $this->assertSame('clicksend_http_500', DeliveryNotification::where('study_id', $failing->id)->sole()->last_error);

        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['body'], 'recibido por SMS'));
        $logged = AuditEvent::all()->toJson().DeliveryNotification::all()->toJson();
        $this->assertStringNotContainsString('clave-secreta', $logged);
        $this->assertStringNotContainsString('+525512345678', $logged);
    }
}
