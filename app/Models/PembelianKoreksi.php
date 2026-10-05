<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warung_id', 'pembelian_id', 'user_id', 'jenis', 'alasan', 'sebelum', 'sesudah', 'idempotency_key', 'payload_hash',
])]
class PembelianKoreksi extends Model
{
    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
        ];
    }

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class, 'pembelian_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
