<?php

namespace App\Services\Notifications;

final class SendResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
    ) {}

    public static function accepted(?string $providerId = null): self
    {
        return new self('accepted', $providerId);
    }

    public static function failed(string $error): self
    {
        return new self('failed', null, $error);
    }

    public static function disabled(): self
    {
        return new self('disabled', null, 'channel_disabled');
    }
}
