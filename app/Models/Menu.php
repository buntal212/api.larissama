<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warung_id', 'kategori_menu_id', 'kode', 'nama', 'harga', 'harga_modal', 'gambar', 'deskripsi', 'aktif'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    public function warung(): BelongsTo
    {
        return $this->belongsTo(Warung::class);
    }

    public function kategoriMenu(): BelongsTo
    {
        return $this->belongsTo(KategoriMenu::class);
    }

    protected function casts(): array
    {
        return ['harga' => 'decimal:2', 'harga_modal' => 'decimal:2', 'aktif' => 'boolean'];
    }
}
