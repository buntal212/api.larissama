<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WarungCustomerSync>
 */
class WarungCustomerSyncFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);

        return [
            'warung_id' => $warung->id,
            'owner_user_id' => $owner->id,
            'external_customer_id' => 'larissama-'.Str::ulid(),
            'status' => 'pending',
            'notice_version' => '2026-10-25.v2',
            'consented_at' => now(),
        ];
    }
}
