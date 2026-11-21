<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasUuids;

    public const TYPE_RESULT = 'result';

    public const TYPE_PRESCRIPTION = 'prescription';

    public const STATUS_PENDING_ASSOCIATION = 'pending_association';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_READY = 'ready_for_publication';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_ASSOCIATION,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_READY,
        self::STATUS_PUBLISHED,
    ];

    public const REJECTION_REASONS = [
        'illegible' => 'Documento ilegible o incompleto',
        'wrong_study' => 'No corresponde al estudio',
        'missing_origin_authorization' => 'Sin evidencia de autorización de origen',
        'duplicate' => 'Duplicado',
        'other' => 'Otro motivo administrativo',
    ];

    public const REVOCATION_REASONS = [
        'publication_error' => 'Error de publicación',
        'wrong_study' => 'Asociación incorrecta',
        'superseded' => 'Sustituido por una versión posterior',
        'other' => 'Otro motivo administrativo',
    ];

    protected $guarded = [];

    protected $hidden = ['original_path', 'delivery_path'];

    protected function casts(): array
    {
        return [
            'check_legible' => 'boolean',
            'check_matches_study' => 'boolean',
            'check_origin_authorization' => 'boolean',
            'associated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function associator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'associated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DeliveryNotification::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->original_mime, 'image/');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_PRESCRIPTION ? 'Receta' : 'Resultado';
    }

    public function shortId(): string
    {
        return mb_strtoupper(mb_substr($this->id, 0, 8));
    }
}
