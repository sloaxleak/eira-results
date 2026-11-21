<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;

/**
 * Transporte SMS simulado (no hay proveedor elegido). Convención de demo: números que
 * terminan en "0000" se rechazan para mostrar el manejo de un aviso fallido.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, text: string}> */
    public array $sent = [];

    public function __construct(private DemoOutbox $outbox) {}

    public function send(string $to, string $text, string $idempotencyKey): SendResult
    {
        if (str_ends_with($to, '0000')) {
            $this->outbox->push('sms', $to, '(no entregado: fallo simulado)', 'failed');

            return SendResult::failed('fake_simulated_failure');
        }

        $this->sent[] = compact('to', 'text');
        $this->outbox->push('sms', $to, $text, 'accepted');

        return SendResult::accepted('fake-'.Str::random(12));
    }
}
