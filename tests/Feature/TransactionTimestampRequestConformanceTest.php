<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionTimestampRequestConformanceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('timestampsWithoutRfc3339Offset')]
    public function test_sale_rejects_timestamp_outside_rfc3339_contract_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $owner->createToken('sale-timestamp-format-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/penjualans', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, ['Idempotency-Key' => 'sale-invalid-timestamp'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');
        $this->assertSame(
            ['Tanggal harus mengikuti format RFC3339 dengan zona waktu.'],
            $response->json('errors.tanggal'),
        );

        $this->assertTransactionTablesAreEmpty();
    }

    #[DataProvider('timestampsWithoutRfc3339Offset')]
    public function test_purchase_rejects_timestamp_outside_rfc3339_contract_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('purchase-timestamp-format-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'rincian' => [['nama_item' => 'Belanja harian', 'subtotal' => '1000.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/pembelians', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $payload, ['Idempotency-Key' => 'purchase-invalid-timestamp'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');
        $this->assertSame(
            ['Tanggal harus mengikuti format RFC3339 dengan zona waktu.'],
            $response->json('errors.tanggal'),
        );

        $this->assertTransactionTablesAreEmpty();
    }

    /** @return array<string, array{string}> */
    public static function timestampsWithoutRfc3339Offset(): array
    {
        return [
            'date only' => ['2026-10-04'],
            'timestamp without zone' => ['2026-10-04T23:30:00'],
            'timestamp with space separator' => ['2026-10-04 23:30:00+00:00'],
        ];
    }

    private function assertTransactionTablesAreEmpty(): void
    {
        $this->assertSame(0, Penjualan::query()->count());
        $this->assertSame(0, PenjualanRinci::query()->count());
        $this->assertSame(0, Pembelian::query()->count());
        $this->assertSame(0, PembelianRinci::query()->count());
    }
}
