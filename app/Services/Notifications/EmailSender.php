<?php

namespace App\Services\Notifications;

interface EmailSender
{
    public function send(string $to, string $subject, string $text, string $idempotencyKey, ?string $html = null): SendResult;
}
