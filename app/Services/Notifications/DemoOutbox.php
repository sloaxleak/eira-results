<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;

/**
 * Bandeja SIMULADA para la demo: conserva en caché, solo durante la vigencia del código,
 * los avisos que los transportes fake "habrían" enviado. Nunca se activa en producción
 * ni con transportes reales, y no escribe en logs.
 */
class DemoOutbox
{
    private const KEY = 'eira.demo_outbox';

    public function enabled(): bool
    {
        return config('eira.demo_outbox') && ! app()->isProduction();
    }

    public function push(string $channel, string $to, string $text, string $status): void
    {
        if (! $this->enabled()) {
            return;
        }

        $messages = $this->all();
        array_unshift($messages, [
            'channel' => $channel,
            'to' => $to,
            'text' => $text,
            'status' => $status,
            'at' => now()->timestamp,
        ]);

        Cache::put(self::KEY, array_slice($messages, 0, 20), now()->addMinutes(min(120, (int) config('eira.otp.ttl_minutes'))));
    }

    public function all(): array
    {
        return $this->enabled() ? Cache::get(self::KEY, []) : [];
    }
}
