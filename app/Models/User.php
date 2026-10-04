<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nama', 'username', 'email', 'password', 'role', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function warung(): BelongsTo
    {
        return $this->belongsTo(Warung::class);
    }

    public function allowsApplicationAccessAt(CarbonImmutable $instantUtc): bool
    {
        if (! $this->aktif) {
            return false;
        }

        if ($this->role === 'superadmin') {
            return $this->warung_id === null;
        }

        if (! in_array($this->role, ['owner', 'manager', 'kasir'], true)) {
            return false;
        }

        if ($this->warung_id === null) {
            return false;
        }

        return $this->warung?->allowsAccessAt($instantUtc) ?? false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
        ];
    }
}
