<?php

namespace Database\Factories;

use App\Models\Pembelian;
use App\Models\PembelianRinci;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PembelianRinci>
 */
class PembelianRinciFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pembelian_id' => Pembelian::factory(),
            'warung_id' => fn (array $attributes): int => (int) Pembelian::query()->whereKey($attributes['pembelian_id'])->value('warung_id'),
            'nama_item' => 'Belanja di pasar',
            'qty' => null,
            'satuan' => null,
            'harga_satuan' => null,
            'subtotal' => '150000.00',
        ];
    }
}
