<?php

namespace App\Models;

use Database\Factories\WarungCustomerSyncFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['warung_id', 'owner_user_id', 'external_customer_id', 'status', 'notice_version', 'consented_at'])]
class WarungCustomerSync extends Model
{
    /** @use HasFactory<WarungCustomerSyncFactory> */
    use HasFactory;

    public function warung(): BelongsTo
    {
        return $this->belongsTo(Warung::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarungCustomerSyncEvent::class);
    }

    protected function casts(): array
    {
        return [
            'consented_at' => 'immutable_datetime',
            'last_enqueued_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
        ];
    }
}
