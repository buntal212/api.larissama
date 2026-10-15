<?php

namespace Tests\Performance;

use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRacePerformanceTest extends TestCase
{
    public function test_two_concurrent_payments_cannot_settle_the_same_order_twice(): void
    {
        $this->assertPerformanceDatabaseIsIsolated();

        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id, 'harga' => '12000.00']);
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'tanggal' => '2026-10-14 10:00:00',
            'subtotal' => '12000.00',
            'total' => '12000.00',
            'bayar' => '0.00',
            'kembalian' => '0.00',
            'metode_pembayaran' => null,
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_lunas',
            'dibayar_pada' => null,
            'pembayaran_user_id' => null,
            'pembayaran_superadmin_id' => null,
            'pembayaran_idempotency_key' => null,
            'pembayaran_payload_hash' => null,
            'pembayaran_idempotency_expires_at' => null,
        ]);
        $sale->rincian()->create([
            'warung_id' => $warung->id,
            'menu_id' => $menu->id,
            'nama_menu' => $menu->nama,
            'harga' => '12000.00',
            'qty' => '1.00',
            'diskon' => '0.00',
            'subtotal' => '12000.00',
        ]);

        $token = $cashier->createToken('optional-payment-race')->plainTextToken;
        $baseUrl = rtrim((string) env('LARISSAMA_PERFORMANCE_BASE_URL'), '/');
        self::assertNotSame('', $baseUrl, 'Set LARISSAMA_PERFORMANCE_BASE_URL through the dedicated Compose file.');

        $triggerName = 'perf_pay_'.Str::lower(Str::random(16));
        $requests = [
            ['bayar' => '12000.00', 'metode_pembayaran' => 'qris', 'key' => 'perf-payment-race-a-'.Str::uuid()],
            ['bayar' => '12000.00', 'metode_pembayaran' => 'qris', 'key' => 'perf-payment-race-b-'.Str::uuid()],
        ];

        try {
            DB::unprepared(
                "CREATE TRIGGER {$triggerName} BEFORE UPDATE ON penjualans FOR EACH ROW SET @larissama_payment_race_delay = IF(OLD.id = {$sale->id} AND OLD.status_pembayaran = 'belum_lunas', SLEEP(1), 0)",
            );

            $responses = $this->sendConcurrentPayments($baseUrl, (int) $sale->id, $token, $requests);
        } finally {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
        }

        $statuses = array_column($responses, 'status');
        sort($statuses);
        self::assertSame([201, 409], $statuses, 'Exactly one concurrent payment should win; the other must see the settled state.');

        $winner = array_values(array_filter($responses, static fn (array $response): bool => $response['status'] === 201))[0];
        $loser = array_values(array_filter($responses, static fn (array $response): bool => $response['status'] === 409))[0];
        self::assertSame('lunas', $winner['body']['data']['status_pembayaran'] ?? null);
        self::assertSame('PENJUALAN_SUDAH_DIPROSES', $loser['body']['code'] ?? null);

        $sale->refresh();
        self::assertSame('lunas', $sale->status_pembayaran);
        self::assertSame('selesai', $sale->status);
        self::assertSame('12000.00', $sale->bayar);
        self::assertContains($sale->pembayaran_idempotency_key, array_column($requests, 'key'));
        self::assertSame(1, Penjualan::query()
            ->where('warung_id', $warung->id)
            ->where('status_pembayaran', 'lunas')
            ->whereNotNull('pembayaran_idempotency_key')
            ->count());
    }

    private function assertPerformanceDatabaseIsIsolated(): void
    {
        self::assertSame('testing', app()->environment(), 'Optional performance tests require APP_ENV=testing.');
        self::assertSame('mysql', DB::connection()->getDriverName(), 'Race checks must run on MySQL.');
        self::assertSame(
            'larissama_performance',
            DB::connection()->getDatabaseName(),
            'Refusing to run race checks outside the dedicated disposable database.',
        );
    }

    /**
     * @param  array<int, array{bayar: string, metode_pembayaran: string, key: string}>  $requests
     * @return array<int, array{status: int, body: array<string, mixed>}>
     */
    private function sendConcurrentPayments(string $baseUrl, int $saleId, string $token, array $requests): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($requests as $request) {
            $handle = curl_init($baseUrl.'/api/v1/penjualans/'.$saleId.'/pembayaran');
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'bayar' => $request['bayar'],
                    'metode_pembayaran' => $request['metode_pembayaran'],
                ], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer '.$token,
                    'Content-Type: application/json',
                    'Idempotency-Key: '.$request['key'],
                ],
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $multiStatus = curl_multi_exec($multiHandle, $active);

            if ($active > 0 && $multiStatus === CURLM_OK) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($active > 0 && $multiStatus === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $body = (string) curl_multi_getcontent($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            self::assertSame(CURLE_OK, curl_errno($handle), 'Payment race request failed: '.curl_error($handle));
            $decodedBody = json_decode($body, true);
            self::assertIsArray($decodedBody, 'Payment response must be JSON. Body='.$body);
            $responses[] = ['status' => $status, 'body' => $decodedBody];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $responses;
    }
}
