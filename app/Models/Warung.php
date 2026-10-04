<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    public function allowsAccessOn(CarbonInterface $localDate): bool
    {
        $date = $localDate->toDateString();

        return $this->aktif
            && ($this->tanggal_mulai === null || $this->tanggal_mulai->toDateString() <= $date)
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
