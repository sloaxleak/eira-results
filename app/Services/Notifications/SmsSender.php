<?php

namespace App\Services\Notifications;

interface SmsSender
{
    public function send(string $to, string $text, string $idempotencyKey): SendResult;
}
