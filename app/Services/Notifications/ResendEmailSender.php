<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Throwable;

class ResendEmailSender implements EmailSender
{
    public function __construct(private ?string $apiKey, private ?string $from) {}

    public function send(string $to, string $subject, string $text, string $idempotencyKey, ?string $html = null): SendResult
    {
        if (! $this->apiKey || ! $this->from) {
            return SendResult::failed('resend_not_configured');
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->timeout(10)
                ->post('https://api.resend.com/emails', array_filter([
                    'from' => $this->from,
                    'to' => [$to],
                    'subject' => $subject,
                    'text' => $text,
                    'html' => $html,
                ]));
        } catch (Throwable) {
            return SendResult::failed('resend_unreachable');
        }

        return $response->successful()
            ? SendResult::accepted($response->json('id'))
            : SendResult::failed('resend_http_'.$response->status());
    }
}
