<?php

namespace Database\Factories;

use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Penjualan>
 */
class PenjualanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'kasir']),
            'warung_id' => fn (array $attributes): int => (int) User::query()->whereKey($attributes['user_id'])->value('warung_id'),
            'no_transaksi' => 'PJ-'.Str::ulid(),
            'idempotency_key' => (string) Str::ulid(),
            'payload_hash' => hash('sha256', Str::uuid()->toString()),
            'tanggal' => now('UTC'),
            'subtotal' => '15000.00',
            'diskon' => '0.00',
            'total' => '15000.00',
            'bayar' => '15000.00',
            'kembalian' => '0.00',
            'metode_pembayaran' => 'cash',
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
            'dibayar_pada' => fn (array $attributes): mixed => $attributes['tanggal'] ?? now('UTC'),
            'pembayaran_user_id' => fn (array $attributes): int => (int) $attributes['user_id'],
            'catatan' => null,
        ];
    }
}
