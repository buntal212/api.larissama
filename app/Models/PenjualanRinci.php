<?php

namespace App\Models;

use Database\Factories\PenjualanRinciFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warung_id', 'penjualan_id', 'menu_id', 'nama_menu', 'harga', 'qty', 'diskon', 'subtotal', 'catatan'])]
class PenjualanRinci extends Model
{
    /** @use HasFactory<PenjualanRinciFactory> */
    use HasFactory;

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'qty' => 'decimal:2',
            'diskon' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
