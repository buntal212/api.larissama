<?php

namespace Database\Factories;

use App\Models\Warung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warung>
 */
class WarungFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('WRG-#####'),
            'nama' => fake()->company(),
            'alamat' => null,
            'telepon' => null,
            'logo' => null,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
            'aktif' => true,
        ];
    }
}
