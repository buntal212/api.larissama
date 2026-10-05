<?php

namespace App\Models;

use Database\Factories\PenjualanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'warung_id', 'user_id', 'no_transaksi', 'idempotency_key', 'payload_hash', 'idempotency_expires_at', 'tanggal',
    'subtotal', 'diskon', 'total', 'bayar', 'kembalian', 'metode_pembayaran', 'status', 'catatan',
])]
class Penjualan extends Model
{
    /** @use HasFactory<PenjualanFactory> */
    use HasFactory;

    public function warung(): BelongsTo
    {
        return $this->belongsTo(Warung::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(PenjualanRinci::class)->orderBy('id');
    }

    public function koreksi(): HasMany
    {
        return $this->hasMany(PenjualanKoreksi::class)->orderBy('id');
    }

    public function retur(): HasMany
    {
        return $this->hasMany(PenjualanRetur::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'immutable_datetime',
            'idempotency_expires_at' => 'immutable_datetime',
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'total' => 'decimal:2',
            'bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }
}
