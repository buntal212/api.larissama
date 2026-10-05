<?php

namespace App\Models;

use Database\Factories\PembelianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'warung_id', 'user_id', 'no_transaksi', 'idempotency_key', 'payload_hash', 'tanggal', 'total', 'status', 'catatan',
])]
class Pembelian extends Model
{
    /** @use HasFactory<PembelianFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'tercatat',
    ];

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
        return $this->hasMany(PembelianRinci::class)->orderBy('id');
    }

    public function koreksi(): HasMany
    {
        return $this->hasMany(PembelianKoreksi::class, 'pembelian_id')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'immutable_datetime',
            'total' => 'decimal:2',
        ];
    }
}
