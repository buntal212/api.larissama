<?php

namespace App\Models;

use Database\Factories\PembelianRinciFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warung_id', 'pembelian_id', 'nama_item', 'qty', 'satuan', 'harga_satuan', 'subtotal'])]
class PembelianRinci extends Model
{
    /** @use HasFactory<PembelianRinciFactory> */
    use HasFactory;

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class);
    }

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
