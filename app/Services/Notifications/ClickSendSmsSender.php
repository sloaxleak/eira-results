<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SMS vía ClickSend REST v3 (https://developers.clicksend.com/docs).
 * Un estado SUCCESS significa «en cola», no entregado: se registra como accepted.
 * Nunca se registra el cuerpo del mensaje, el número ni las credenciales.
 */
class ClickSendSmsSender implements SmsSender
{
    private const ENDPOINT = 'https://rest.clicksend.com/v3/sms/send';

    public function __construct(
        private ?string $username,
        private ?string $apiKey,
        private ?string $from = null,
    ) {}

    public function send(string $to, string $text, string $idempotencyKey): SendResult
    {
        if (! $this->username || ! $this->apiKey) {
            return SendResult::failed('clicksend_not_configured');
        }

        $message = array_filter([
            'source' => 'eira-resultados',
            'to' => $to,
            'body' => $text,
            'from' => $this->from ?: null,
        ]);

        try {
            $response = Http::withBasicAuth($this->username, $this->apiKey)
                ->acceptJson()
                ->timeout(10)
                ->post(self::ENDPOINT, ['messages' => [$message]]);
        } catch (Throwable) {
            return SendResult::failed('clicksend_unreachable');
        }

        if (! $response->successful()) {
            return SendResult::failed('clicksend_http_'.$response->status());
        }

        $status = (string) $response->json('data.messages.0.status', $response->json('response_code', 'UNKNOWN'));

        return $status === 'SUCCESS'
            ? SendResult::accepted($response->json('data.messages.0.message_id'))
            : SendResult::failed('clicksend_'.mb_strtolower(mb_substr(preg_replace('/[^A-Z_]/', '', $status), 0, 50)));
    }
}
