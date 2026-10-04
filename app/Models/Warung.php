<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
