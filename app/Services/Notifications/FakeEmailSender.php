<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;

/**
 * Transporte simulado. Convención de demo: direcciones que empiezan con "fallo" se rechazan
 * para mostrar que un aviso fallido no afecta la disponibilidad del documento.
 */
class FakeEmailSender implements EmailSender
{
    /** @var list<array{to: string, subject: string, text: string, html: ?string}> */
    public array $sent = [];

    public function __construct(private DemoOutbox $outbox) {}

    public function send(string $to, string $subject, string $text, string $idempotencyKey, ?string $html = null): SendResult
    {
        if (str_starts_with($to, 'fallo')) {
            $this->outbox->push('email', $to, '(no entregado: fallo simulado)', 'failed');

            return SendResult::failed('fake_simulated_failure');
        }

        $this->sent[] = compact('to', 'subject', 'text', 'html');
        $this->outbox->push('email', $to, $text, 'accepted');

        return SendResult::accepted('fake-'.Str::random(12));
    }
}
