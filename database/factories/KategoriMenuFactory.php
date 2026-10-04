<?php

namespace Database\Factories;

use App\Models\KategoriMenu;
use App\Models\Warung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KategoriMenu>
 */
class KategoriMenuFactory extends Factory
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
            'nama' => fake()->unique()->words(2, true),
            'urutan' => fake()->numberBetween(0, 20),
            'aktif' => true,
        ];
    }
}
