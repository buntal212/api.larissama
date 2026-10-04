<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenjualanRinci>
 */
class PenjualanRinciFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penjualan_id' => Penjualan::factory(),
            'warung_id' => fn (array $attributes): int => (int) Penjualan::query()->whereKey($attributes['penjualan_id'])->value('warung_id'),
            'menu_id' => function (array $attributes): int {
                $warungId = (int) $attributes['warung_id'];

                return (int) Menu::factory()->create(['warung_id' => $warungId])->getKey();
            },
            'nama_menu' => 'Menu contoh',
            'harga' => '15000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '15000.00',
            'catatan' => null,
        ];
    }
}
