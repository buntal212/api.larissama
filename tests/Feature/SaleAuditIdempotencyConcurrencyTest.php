<?php

namespace Tests\Feature;

use App\Models\Penjualan;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SaleAuditIdempotencyConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_correction_requests_with_same_key_create_one_audit_event(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $sale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $cashier->id]);
        $key = 'concurrent-sale-correction-'.Str::uuid();

        $responses = $this->sendOverlappingAuditRequests(
            'penjualan_koreksi',
            'penjualan_koreksis',
            $sale,
            $owner,
            $key,
            ['alasan' => 'Catatan struk salah.', 'catatan' => 'Catatan yang benar.'],
        );

        $this->assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
        $this->assertSame(1, DB::table('penjualan_koreksis')->where('penjualan_id', $sale->id)->where('idempotency_key', $key)->count());
        $this->assertSame('Catatan yang benar.', $sale->refresh()->catatan);
    }

    public function test_overlapping_return_requests_with_same_key_create_one_return_event(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['warung_id' => $warung->id, 'role' => 'kasir']);
        $sale = Penjualan::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $cashier->id,
            'total' => '100.00',
        ]);
        $key = 'concurrent-sale-return-'.Str::uuid();

        $responses = $this->sendOverlappingAuditRequests(
            'penjualan_retur',
            'penjualan_returs',
            $sale,
            $owner,
            $key,
            ['nominal' => '25.00', 'alasan' => 'Satu item dikembalikan.'],
        );

        $this->assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
        $this->assertSame(1, DB::table('penjualan_returs')->where('penjualan_id', $sale->id)->where('idempotency_key', $key)->count());
        $this->assertSame('diretur_sebagian', $sale->refresh()->status);
    }

    /** @param array<string, mixed> $payload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function sendOverlappingAuditRequests(
        string $transactionType,
        string $eventTable,
        Penjualan $sale,
        User $actor,
        string $idempotencyKey,
        array $payload,
    ): array {
        $barrierId = (string) Str::uuid();
        $barrierPrefix = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId;
        $triggerName = 'test_delay_'.Str::lower(Str::random(16));
        $processes = [];

        try {
            DB::unprepared("CREATE TRIGGER {$triggerName} BEFORE INSERT ON {$eventTable} FOR EACH ROW SET @test_audit_delay = SLEEP(1)");

            $processEnvironment = getenv();
            $processEnvironment = is_array($processEnvironment) ? $processEnvironment : [];
            $processEnvironment['APP_ENV'] = 'testing';
            $processEnvironment['LARISSAMA_TEST_BARRIER_ID'] = $barrierId;
            $processEnvironment['LARISSAMA_TEST_IDEMPOTENCY_KEY'] = $idempotencyKey;
            $processEnvironment['LARISSAMA_TEST_TOKEN'] = $actor->createToken('sale-audit-concurrency')->plainTextToken;
            $processEnvironment['LARISSAMA_TEST_TRANSACTION_TYPE'] = $transactionType;
            $processEnvironment['LARISSAMA_TEST_SALE_ID'] = (string) $sale->getKey();
            $processEnvironment['LARISSAMA_TEST_PAYLOAD'] = json_encode($payload, JSON_THROW_ON_ERROR);

            for ($requestNumber = 0; $requestNumber < 2; $requestNumber++) {
                $process = new Process(['php', 'tests/Support/concurrent-http-worker.php'], base_path(), $processEnvironment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            $responses = [];

            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), 'Worker stdout='.$process->getOutput().'; stderr='.$process->getErrorOutput());
                $responses[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            $this->assertSame([201, 201], array_column($responses, 'status'));
            $this->assertLessThanOrEqual(min(array_column($responses, 'request_finished_at')), max(array_column($responses, 'request_started_at')));
            $this->assertCount(2, glob($barrierPrefix.'.*') ?: []);

            return $responses;
        } finally {
            foreach ($processes as $process) {
                $process->stop(1);
            }

            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");

            foreach (glob($barrierPrefix.'.*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
        }
    }
}
