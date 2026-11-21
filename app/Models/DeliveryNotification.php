<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNotification extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_UNKNOWN = 'unknown';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_attempt_at' => 'datetime'];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(StudyContact::class, 'study_contact_id');
    }
}
