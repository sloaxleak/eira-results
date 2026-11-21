<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'disabled_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_PUBLISHER = 'publisher';

    public const ROLE_ADMIN = 'admin';

    public const STAFF_ROLES = [self::ROLE_OPERATOR, self::ROLE_PUBLISHER, self::ROLE_ADMIN];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isStaff(): bool
    {
        return ! $this->disabled_at && in_array($this->role, self::STAFF_ROLES, true);
    }

    public static function roleOptions(): array
    {
        return collect(self::STAFF_ROLES)->mapWithKeys(fn (string $role) => [$role => (new self(['role' => $role]))->roleLabel()])->all();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_OPERATOR => 'Operación administrativa',
            self::ROLE_PUBLISHER => 'Responsable de publicación',
            self::ROLE_ADMIN => 'Administración',
            default => 'Sin rol',
        };
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
