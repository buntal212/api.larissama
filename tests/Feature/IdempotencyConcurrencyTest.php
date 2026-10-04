<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class IdempotencyConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_purchase_requests_with_same_key_create_one_transaction(): void
    {
        $warung = Warung::factory()->create();
        $manager = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $manager->createToken('concurrency-test')->plainTextToken;
        $idempotencyKey = 'concurrent-purchase-'.Str::uuid();
        $barrierId = (string) Str::uuid();
        $barrierPrefix = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId;
        $triggerName = 'test_delay_'.Str::lower(Str::random(16));
        $processes = [];

        try {
            DB::unprepared(
                "CREATE TRIGGER {$triggerName} BEFORE INSERT ON pembelians FOR EACH ROW SET @test_purchase_delay = SLEEP(1)"
            );

            $processEnvironment = getenv();
            $processEnvironment = is_array($processEnvironment) ? $processEnvironment : [];
            $processEnvironment['APP_ENV'] = 'testing';
            $processEnvironment['LARISSAMA_TEST_BARRIER_ID'] = $barrierId;
            $processEnvironment['LARISSAMA_TEST_IDEMPOTENCY_KEY'] = $idempotencyKey;
            $processEnvironment['LARISSAMA_TEST_TOKEN'] = $token;
            $processEnvironment['LARISSAMA_TEST_TRANSACTION_TYPE'] = 'pembelian';
            $processEnvironment['LARISSAMA_TEST_PAYLOAD'] = json_encode([
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
            ], JSON_THROW_ON_ERROR);

            for ($requestNumber = 0; $requestNumber < 2; $requestNumber++) {
                $process = new Process(['php', 'tests/Support/concurrent-http-worker.php'], base_path(), $processEnvironment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            $startedAt = microtime(true);
            $responses = [];

            foreach ($processes as $process) {
                $process->wait();

                self::assertSame(
                    0,
                    $process->getExitCode(),
                    'Worker stdout='.$process->getOutput().'; stderr='.$process->getErrorOutput()
                );
                $responses[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            $elapsedSeconds = microtime(true) - $startedAt;
            self::assertSame([201, 201], array_column($responses, 'status'));
            self::assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            self::assertLessThan(3.0, $elapsedSeconds, 'Dua HTTP Kernel harus melewati barrier sebelum operasi INSERT.');
            self::assertCount(2, glob($barrierPrefix.'.*') ?: []);
            self::assertSame(1, Pembelian::query()
                ->where('warung_id', $warung->id)
                ->where('user_id', $manager->id)
                ->where('idempotency_key', $idempotencyKey)
                ->count());
            self::assertSame(1, Pembelian::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail()
                ->rincian()
                ->count());
        } finally {
            foreach ($processes as $process) {
                $process->stop(1);
            }

            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");

            DB::table('pembelian_rincis')->where('warung_id', $warung->id)->delete();
            DB::table('pembelians')->where('warung_id', $warung->id)->delete();
            $manager->tokens()->delete();
            $manager->delete();
            $warung->delete();

            foreach (glob($barrierPrefix.'.*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
        }
    }

    public function test_overlapping_sale_requests_with_same_key_create_one_transaction(): void
    {
        $warung = Warung::factory()->create();
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $menu = Menu::factory()->create(['warung_id' => $warung->id]);
        $token = $cashier->createToken('concurrency-test')->plainTextToken;
        $idempotencyKey = 'concurrent-sale-'.Str::uuid();
        $barrierId = (string) Str::uuid();
        $barrierPrefix = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId;
        $triggerName = 'test_delay_'.Str::lower(Str::random(16));
        $processes = [];

        try {
            DB::unprepared(
                "CREATE TRIGGER {$triggerName} BEFORE INSERT ON penjualans FOR EACH ROW SET @test_sale_delay = SLEEP(1)"
            );

            $processEnvironment = getenv();
            $processEnvironment = is_array($processEnvironment) ? $processEnvironment : [];
            $processEnvironment['APP_ENV'] = 'testing';
            $processEnvironment['LARISSAMA_TEST_BARRIER_ID'] = $barrierId;
            $processEnvironment['LARISSAMA_TEST_IDEMPOTENCY_KEY'] = $idempotencyKey;
            $processEnvironment['LARISSAMA_TEST_TOKEN'] = $token;
            $processEnvironment['LARISSAMA_TEST_TRANSACTION_TYPE'] = 'penjualan';
            $processEnvironment['LARISSAMA_TEST_PAYLOAD'] = json_encode([
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'bayar' => '15000.00',
                'metode_pembayaran' => 'cash',
                'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
            ], JSON_THROW_ON_ERROR);

            for ($requestNumber = 0; $requestNumber < 2; $requestNumber++) {
                $process = new Process(['php', 'tests/Support/concurrent-http-worker.php'], base_path(), $processEnvironment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            $startedAt = microtime(true);
            $responses = [];

            foreach ($processes as $process) {
                $process->wait();

                self::assertSame(
                    0,
                    $process->getExitCode(),
                    'Worker stdout='.$process->getOutput().'; stderr='.$process->getErrorOutput()
                );
                $responses[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            $elapsedSeconds = microtime(true) - $startedAt;
            self::assertSame([201, 201], array_column($responses, 'status'));
            self::assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            self::assertSame($responses[0]['data']['no_transaksi'], $responses[1]['data']['no_transaksi']);
            self::assertLessThan(3.0, $elapsedSeconds, 'Dua HTTP Kernel harus melewati barrier sebelum operasi INSERT.');
            self::assertCount(2, glob($barrierPrefix.'.*') ?: []);
            self::assertSame(1, Penjualan::query()
                ->where('warung_id', $warung->id)
                ->where('user_id', $cashier->id)
                ->where('idempotency_key', $idempotencyKey)
                ->count());
            self::assertSame(1, Penjualan::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail()
                ->rincian()
                ->count());
        } finally {
            foreach ($processes as $process) {
                $process->stop(1);
            }

            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");

            DB::table('penjualan_rincis')->where('warung_id', $warung->id)->delete();
            DB::table('penjualans')->where('warung_id', $warung->id)->delete();
            DB::table('menus')->where('warung_id', $warung->id)->delete();
            DB::table('kategori_menus')->where('warung_id', $warung->id)->delete();
            $cashier->tokens()->delete();
            $cashier->delete();
            $warung->delete();

            foreach (glob($barrierPrefix.'.*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
        }
    }

    public function beginDatabaseTransaction()
    {
        // Child HTTP Kernel processes need committed fixtures and writes.
    }
}
