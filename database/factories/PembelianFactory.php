<?php

namespace Database\Factories;

use App\Models\Pembelian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pembelian>
 */
class PembelianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'manager']),
            'warung_id' => fn (array $attributes): int => (int) User::query()->whereKey($attributes['user_id'])->value('warung_id'),
            'no_transaksi' => 'PB-'.Str::ulid(),
            'idempotency_key' => (string) Str::ulid(),
            'payload_hash' => hash('sha256', Str::uuid()->toString()),
            'tanggal' => now('UTC'),
            'total' => '150000.00',
            'catatan' => null,
        ];
    }
}
