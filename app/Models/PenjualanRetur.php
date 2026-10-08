<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warung_id', 'penjualan_id', 'user_id', 'superadmin_id', 'nominal', 'alasan',
    'idempotency_key', 'payload_hash', 'idempotency_expires_at',
])]
class PenjualanRetur extends Model
{
    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'idempotency_expires_at' => 'immutable_datetime',
        ];
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
