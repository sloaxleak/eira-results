<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Study extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['taken_at' => 'date'];
    }

    public static function generateFolio(): string
    {
        $prefix = 'EIRA-'.now()->year.'-';
        $next = self::where('folio', 'like', $prefix.'%')->count();

        do {
            $folio = $prefix.str_pad((string) ++$next, 5, '0', STR_PAD_LEFT);
        } while (self::where('folio', $folio)->exists());

        return $folio;
    }

    public static function normalizeFolio(string $input): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', trim($input)));
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(StudyContact::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DeliveryNotification::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(AccessChallenge::class);
    }

    public function hasPublishedDocument(): bool
    {
        return $this->documents()->where('status', Document::STATUS_PUBLISHED)->exists();
    }
}
