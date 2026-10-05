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
use Illuminate\Support\Facades\DB;
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

    #[DataProvider('calendarInvalidTimestamps')]
    public function test_sale_rejects_invalid_calendar_date_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $owner->createToken('sale-invalid-calendar-date-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/penjualans', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, ['Idempotency-Key' => 'sale-invalid-calendar-date'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertTransactionTablesAreEmpty();
    }

    #[DataProvider('calendarInvalidTimestamps')]
    public function test_purchase_rejects_invalid_calendar_date_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('purchase-invalid-calendar-date-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'rincian' => [['nama_item' => 'Belanja harian', 'subtotal' => '1000.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/pembelians', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $payload, ['Idempotency-Key' => 'purchase-invalid-calendar-date'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');

        $this->assertTransactionTablesAreEmpty();
    }

    #[DataProvider('offsetsOutsideRfc3339Range')]
    public function test_sale_rejects_offset_outside_rfc3339_range_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $owner->createToken('sale-offset-range-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/penjualans', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, ['Idempotency-Key' => 'sale-invalid-offset-range'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');

        $this->assertTransactionTablesAreEmpty();
    }

    #[DataProvider('offsetsOutsideRfc3339Range')]
    public function test_purchase_rejects_offset_outside_rfc3339_range_without_writing(string $timestamp): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('purchase-offset-range-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'rincian' => [['nama_item' => 'Belanja harian', 'subtotal' => '1000.00']],
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/pembelians', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $payload, ['Idempotency-Key' => 'purchase-invalid-offset-range'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('tanggal');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');

        $this->assertTransactionTablesAreEmpty();
    }

    #[DataProvider('validRfc3339OffsetBoundaries')]
    public function test_sale_accepts_valid_rfc3339_offset_boundaries(string $timestamp, string $utcValue): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '1000.00']);
        $token = $owner->createToken('sale-valid-offset-range-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'bayar' => '1000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
        ];
        $headers = ['Idempotency-Key' => 'sale-valid-offset-range'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/penjualans', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/penjualans', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.tanggal', str_replace(' ', 'T', $utcValue).'.000000Z');
        $this->assertOperationResponseMatchesOpenApi($response, '/penjualans', 'post');
        $this->assertSame($utcValue, DB::table('penjualans')->where('id', $response->json('data.id'))->value('tanggal'));
    }

    #[DataProvider('validRfc3339OffsetBoundaries')]
    public function test_purchase_accepts_valid_rfc3339_offset_boundaries(string $timestamp, string $utcValue): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('purchase-valid-offset-range-test')->plainTextToken;
        $payload = [
            'tanggal' => $timestamp,
            'rincian' => [['nama_item' => 'Belanja harian', 'subtotal' => '1000.00']],
        ];
        $headers = ['Idempotency-Key' => 'purchase-valid-offset-range'];

        $this->assertOperationRequestMatchesOpenApi($payload, $headers, '/pembelians', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/pembelians', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.tanggal', str_replace(' ', 'T', $utcValue).'.000000Z');
        $this->assertOperationResponseMatchesOpenApi($response, '/pembelians', 'post');
        $this->assertSame($utcValue, DB::table('pembelians')->where('id', $response->json('data.id'))->value('tanggal'));
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

    /** @return array<string, array{string}> */
    public static function offsetsOutsideRfc3339Range(): array
    {
        return [
            'offset hour above maximum' => ['2026-10-04T23:30:00+24:00'],
            'offset minute above maximum' => ['2026-10-04T23:30:00+00:60'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function calendarInvalidTimestamps(): array
    {
        return [
            'february thirtieth' => ['2026-02-30T12:00:00Z'],
            'february thirty first' => ['2026-02-31T12:00:00Z'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function validRfc3339OffsetBoundaries(): array
    {
        return [
            'utc designator' => ['2026-10-04T23:30:00Z', '2026-10-04 23:30:00'],
            'maximum offset' => ['2026-10-04T23:30:00+23:59', '2026-10-03 23:31:00'],
            'leap day' => ['2024-02-29T12:00:00Z', '2024-02-29 12:00:00'],
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
