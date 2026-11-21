<?php

namespace App\Services\Notifications;

class DisabledSmsSender implements SmsSender
{
    public function send(string $to, string $text, string $idempotencyKey): SendResult
    {
        return SendResult::disabled();
    }
}
