<?php

namespace App\Models;

use Database\Factories\KategoriMenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['warung_id', 'nama', 'urutan', 'aktif'])]
class KategoriMenu extends Model
{
    /** @use HasFactory<KategoriMenuFactory> */
    use HasFactory;

    public function warung(): BelongsTo
    {
        return $this->belongsTo(Warung::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'aktif' => 'boolean'];
    }
}
