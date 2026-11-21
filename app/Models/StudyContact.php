<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyContact extends Model
{
    use HasUuids;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function masked(): string
    {
        $value = (string) $this->value;

        if ($this->channel === self::CHANNEL_EMAIL && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'•••@'.$domain;
        }

        return '•••• '.mb_substr($value, -2);
    }
}
