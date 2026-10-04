<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WarungFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kode',
    'nama',
    'alamat',
    'telepon',
    'logo',
    'timezone',
    'tanggal_mulai',
    'tanggal_berakhir',
    'aktif',
])]
class Warung extends Model
{
    /** @use HasFactory<WarungFactory> */
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function kategoriMenus(): HasMany
    {
        return $this->hasMany(KategoriMenu::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function allowsAccessAt(CarbonImmutable $instantUtc): bool
    {
        if (! $this->aktif || ! is_string($this->timezone) || $this->timezone === '') {
            return false;
        }

        if (! in_array($this->timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            return false;
        }

        $date = $instantUtc->setTimezone($this->timezone)->toDateString();

        return ($this->tanggal_mulai === null || $this->tanggal_mulai->toDateString() <= $date)
            && ($this->tanggal_berakhir === null || $this->tanggal_berakhir->toDateString() >= $date);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_berakhir' => 'date',
            'aktif' => 'boolean',
        ];
    }
}
