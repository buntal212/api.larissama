<?php

namespace Database\Factories;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Warung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warung_id' => Warung::factory(),
            'kategori_menu_id' => function (array $attributes): int {
                return (int) KategoriMenu::factory()
                    ->create(['warung_id' => $attributes['warung_id']])
                    ->getKey();
            },
            'kode' => strtoupper(fake()->unique()->bothify('MNL###??')),
            'nama' => fake()->words(2, true),
            'harga' => '15000.00',
            'harga_modal' => null,
            'gambar' => null,
            'deskripsi' => null,
            'aktif' => true,
        ];
    }
}
