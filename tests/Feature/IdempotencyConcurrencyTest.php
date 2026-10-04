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
use PHPUnit\Framework\Attributes\DataProvider;
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

            self::assertSame([201, 201], array_column($responses, 'status'));
            self::assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            $this->assertWorkerRequestsOverlap($responses);
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

            self::assertSame([201, 201], array_column($responses, 'status'));
            self::assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            self::assertSame($responses[0]['data']['no_transaksi'], $responses[1]['data']['no_transaksi']);
            $this->assertWorkerRequestsOverlap($responses);
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

    #[DataProvider('differentPayloadTransactionTypes')]
    public function test_overlapping_different_payloads_with_same_key_create_one_and_conflict(string $transactionType): void
    {
        $warung = Warung::factory()->create();
        $role = $transactionType === 'penjualan' ? 'kasir' : 'manager';
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $menu = $transactionType === 'penjualan'
            ? Menu::factory()->create(['warung_id' => $warung->id])
            : null;
        $token = $actor->createToken('concurrency-conflict-test')->plainTextToken;
        $idempotencyKey = 'concurrent-conflict-'.Str::uuid();
        $barrierId = (string) Str::uuid();
        $barrierPrefix = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId;
        $headerTable = $transactionType === 'penjualan' ? 'penjualans' : 'pembelians';
        $detailTable = $transactionType === 'penjualan' ? 'penjualan_rincis' : 'pembelian_rincis';
        $triggerName = 'test_delay_'.Str::lower(Str::random(16));
        $processes = [];
        $payload = $transactionType === 'penjualan'
            ? [
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'bayar' => '15000.00',
                'metode_pembayaran' => 'cash',
                'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
            ]
            : [
                'tanggal' => '2026-10-04T10:00:00+07:00',
                'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
            ];
        $payloads = [
            [...$payload, 'catatan' => 'Percobaan A'],
            [...$payload, 'catatan' => 'Percobaan B'],
        ];

        try {
            DB::unprepared(
                "CREATE TRIGGER {$triggerName} BEFORE INSERT ON {$headerTable} FOR EACH ROW SET @test_conflict_delay = SLEEP(1)"
            );

            $baseEnvironment = getenv();
            $baseEnvironment = is_array($baseEnvironment) ? $baseEnvironment : [];
            $baseEnvironment['APP_ENV'] = 'testing';
            $baseEnvironment['LARISSAMA_TEST_BARRIER_ID'] = $barrierId;
            $baseEnvironment['LARISSAMA_TEST_IDEMPOTENCY_KEY'] = $idempotencyKey;
            $baseEnvironment['LARISSAMA_TEST_TOKEN'] = $token;
            $baseEnvironment['LARISSAMA_TEST_TRANSACTION_TYPE'] = $transactionType;

            foreach ($payloads as $requestPayload) {
                $processEnvironment = $baseEnvironment;
                $processEnvironment['LARISSAMA_TEST_PAYLOAD'] = json_encode($requestPayload, JSON_THROW_ON_ERROR);
                $process = new Process(['php', 'tests/Support/concurrent-http-worker.php'], base_path(), $processEnvironment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

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

            $statuses = array_column($responses, 'status');
            sort($statuses);
            self::assertSame([201, 409], $statuses);

            $created = collect($responses)->firstWhere('status', 201);
            $conflict = collect($responses)->firstWhere('status', 409);
            self::assertIsArray($created['data']);
            self::assertSame('IDEMPOTENCY_KEY_REUSED', $conflict['code']);

            $this->assertWorkerRequestsOverlap($responses);
            self::assertCount(2, glob($barrierPrefix.'.*') ?: []);

            $model = $transactionType === 'penjualan' ? Penjualan::class : Pembelian::class;
            $transaction = $model::query()
                ->where('warung_id', $warung->id)
                ->where('user_id', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
            self::assertSame((string) $transaction->id, $created['data']['id']);
            self::assertSame(1, $model::query()
                ->where('warung_id', $warung->id)
                ->where('user_id', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->count());
            self::assertSame(1, $transaction->rincian()->count());
        } finally {
            foreach ($processes as $process) {
                $process->stop(1);
            }

            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");

            DB::table($detailTable)->where('warung_id', $warung->id)->delete();
            DB::table($headerTable)->where('warung_id', $warung->id)->delete();

            if ($menu !== null) {
                DB::table('menus')->where('id', $menu->id)->delete();
                DB::table('kategori_menus')->where('warung_id', $warung->id)->delete();
            }

            $actor->tokens()->delete();
            $actor->delete();
            $warung->delete();

            foreach (glob($barrierPrefix.'.*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
        }
    }

    #[DataProvider('separateIdempotencyScopes')]
    public function test_same_key_in_separate_valid_scopes_creates_independent_transactions(string $scopeCase): void
    {
        $warungA = Warung::factory()->create();
        $warungB = $scopeCase === 'different-tenants' ? Warung::factory()->create() : $warungA;
        $transactionTypes = match ($scopeCase) {
            'different-users' => ['penjualan', 'penjualan'],
            'different-tenants' => ['pembelian', 'pembelian'],
            'different-endpoints' => ['penjualan', 'pembelian'],
        };
        $warungs = [$warungA, $warungB];
        $actors = [];
        $menus = [];

        foreach ($transactionTypes as $index => $transactionType) {
            $warung = $warungs[$index];
            $role = $transactionType === 'penjualan' ? 'kasir' : 'manager';
            $actors[] = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);

            if ($transactionType === 'penjualan' && ! isset($menus[$warung->id])) {
                $menus[$warung->id] = Menu::factory()->create(['warung_id' => $warung->id]);
            }
        }

        $idempotencyKey = 'shared-scope-'.Str::uuid();
        $barrierId = (string) Str::uuid();
        $warungIds = collect($warungs)->pluck('id')->unique()->all();
        $triggerNames = [];

        try {
            if (count(array_unique($transactionTypes)) > 1) {
                $triggerNames = $this->createDelayedInsertTriggers($transactionTypes);
            }

            $requests = [];

            foreach ($transactionTypes as $index => $transactionType) {
                $actor = $actors[$index];
                $payload = $transactionType === 'penjualan'
                    ? [
                        'tanggal' => '2026-10-04T10:00:00+07:00',
                        'bayar' => '15000.00',
                        'metode_pembayaran' => 'cash',
                        'rincian' => [['menu_id' => (string) $menus[$actor->warung_id]->id, 'qty' => '1.00']],
                    ]
                    : [
                        'tanggal' => '2026-10-04T10:00:00+07:00',
                        'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
                    ];

                $requests[] = [
                    'token' => $actor->createToken('separate-scope-test')->plainTextToken,
                    'idempotency_key' => $idempotencyKey,
                    'transaction_type' => $transactionType,
                    'payload' => $payload,
                ];
            }

            $result = $this->runConcurrentRequests($barrierId, $requests);
            $responses = $result['responses'];
            $statuses = array_column($responses, 'status');
            sort($statuses);

            self::assertSame([201, 201], $statuses);
            self::assertNotSame($responses[0]['data']['no_transaksi'], $responses[1]['data']['no_transaksi']);
            $this->assertWorkerRequestsOverlap($responses);
            self::assertSame(2, $result['arrival_count']);

            if ($transactionTypes[0] === $transactionTypes[1]) {
                self::assertNotSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            }

            foreach ($requests as $index => $request) {
                $model = $request['transaction_type'] === 'penjualan' ? Penjualan::class : Pembelian::class;
                $actor = $actors[$index];
                $transaction = $model::query()
                    ->where('warung_id', $actor->warung_id)
                    ->where('user_id', $actor->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();

                self::assertSame((string) $transaction->id, $responses[$index]['data']['id']);
                self::assertSame($transaction->no_transaksi, $responses[$index]['data']['no_transaksi']);
                self::assertSame(1, $transaction->rincian()->count());
            }
        } finally {
            $this->dropDelayedInsertTriggers($triggerNames);
            $this->deleteTransactionFixtures($warungIds);

            foreach ($actors as $actor) {
                $actor->tokens()->delete();
                $actor->delete();
            }

            foreach ($warungIds as $warungId) {
                DB::table('menus')->where('warung_id', $warungId)->delete();
                DB::table('kategori_menus')->where('warung_id', $warungId)->delete();
                DB::table('warungs')->where('id', $warungId)->delete();
            }
        }
    }

    #[DataProvider('transactionTypes')]
    public function test_concurrent_distinct_requests_receive_unique_transaction_numbers(string $transactionType): void
    {
        $warung = Warung::factory()->create();
        $role = $transactionType === 'penjualan' ? 'kasir' : 'manager';
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $menu = $transactionType === 'penjualan'
            ? Menu::factory()->create(['warung_id' => $warung->id])
            : null;
        $token = $actor->createToken('distinct-transaction-test')->plainTextToken;
        $idempotencyKeys = ['distinct-a-'.Str::uuid(), 'distinct-b-'.Str::uuid()];
        $barrierId = (string) Str::uuid();
        $triggerNames = [];
        $table = $transactionType === 'penjualan' ? 'penjualans' : 'pembelians';
        $model = $transactionType === 'penjualan' ? Penjualan::class : Pembelian::class;

        try {
            $basePayload = $transactionType === 'penjualan'
                ? [
                    'tanggal' => '2026-10-04T10:00:00+07:00',
                    'bayar' => '15000.00',
                    'metode_pembayaran' => 'cash',
                    'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
                ]
                : [
                    'tanggal' => '2026-10-04T10:00:00+07:00',
                    'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
                ];
            $requests = [];

            foreach ($idempotencyKeys as $index => $idempotencyKey) {
                $requests[] = [
                    'token' => $token,
                    'idempotency_key' => $idempotencyKey,
                    'transaction_type' => $transactionType,
                    'payload' => [...$basePayload, 'catatan' => 'Transaksi berbeda '.$index],
                ];
            }

            $result = $this->runConcurrentRequests($barrierId, $requests);
            $responses = $result['responses'];
            $statuses = array_column($responses, 'status');
            $transactionNumbers = array_column(array_column($responses, 'data'), 'no_transaksi');
            sort($statuses);

            self::assertSame([201, 201], $statuses);
            self::assertNotSame($responses[0]['data']['id'], $responses[1]['data']['id']);
            self::assertNotSame($transactionNumbers[0], $transactionNumbers[1]);
            $this->assertWorkerRequestsOverlap($responses);
            self::assertSame(2, $result['arrival_count']);

            foreach ($requests as $index => $request) {
                $transaction = $model::query()
                    ->where('warung_id', $warung->id)
                    ->where('user_id', $actor->id)
                    ->where('idempotency_key', $request['idempotency_key'])
                    ->firstOrFail();

                self::assertSame((string) $transaction->id, $responses[$index]['data']['id']);
                self::assertSame($transaction->no_transaksi, $responses[$index]['data']['no_transaksi']);
                self::assertSame(1, $transaction->rincian()->count());
            }

            self::assertSame(2, $model::query()
                ->where('warung_id', $warung->id)
                ->where('user_id', $actor->id)
                ->whereIn('idempotency_key', $idempotencyKeys)
                ->distinct('no_transaksi')
                ->count('no_transaksi'));
            self::assertSame(2, DB::table($table)
                ->where('warung_id', $warung->id)
                ->whereIn('idempotency_key', $idempotencyKeys)
                ->count());
        } finally {
            $this->dropDelayedInsertTriggers($triggerNames);
            $this->deleteTransactionFixtures([$warung->id]);
            $actor->tokens()->delete();
            $actor->delete();

            if ($menu !== null) {
                DB::table('menus')->where('warung_id', $warung->id)->delete();
                DB::table('kategori_menus')->where('warung_id', $warung->id)->delete();
            }

            DB::table('warungs')->where('id', $warung->id)->delete();
        }
    }

    public static function separateIdempotencyScopes(): array
    {
        return [
            'different-users' => ['different-users'],
            'different-tenants' => ['different-tenants'],
            'different-endpoints' => ['different-endpoints'],
        ];
    }

    public static function transactionTypes(): array
    {
        return [
            'sale' => ['penjualan'],
            'purchase' => ['pembelian'],
        ];
    }

    /**
     * @param  list<string>  $transactionTypes
     * @return list<string>
     */
    private function createDelayedInsertTriggers(array $transactionTypes): array
    {
        $tables = array_unique(array_map(fn (string $type): string => match ($type) {
            'penjualan' => 'penjualans',
            'pembelian' => 'pembelians',
            default => throw new \InvalidArgumentException("Unsupported transaction type {$type}."),
        }, $transactionTypes));
        $triggerNames = [];

        foreach ($tables as $table) {
            $triggerName = 'test_delay_'.Str::lower(Str::random(16));
            DB::unprepared(
                "CREATE TRIGGER {$triggerName} BEFORE INSERT ON {$table} FOR EACH ROW SET @test_scope_delay = SLEEP(2)"
            );
            $triggerNames[] = $triggerName;
        }

        return $triggerNames;
    }

    /**
     * @param  list<array{token: string, idempotency_key: string, transaction_type: string, payload: array<string, mixed>}>  $requests
     * @return array{responses: list<array<string, mixed>>, arrival_count: int}
     */
    private function runConcurrentRequests(string $barrierId, array $requests): array
    {
        $barrierPrefix = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId;
        $processes = [];

        try {
            foreach ($requests as $workerRequest) {
                $environment = getenv();
                $environment = is_array($environment) ? $environment : [];
                $environment['APP_ENV'] = 'testing';
                $environment['LARISSAMA_TEST_BARRIER_ID'] = $barrierId;
                $environment['LARISSAMA_TEST_IDEMPOTENCY_KEY'] = $workerRequest['idempotency_key'];
                $environment['LARISSAMA_TEST_TOKEN'] = $workerRequest['token'];
                $environment['LARISSAMA_TEST_TRANSACTION_TYPE'] = $workerRequest['transaction_type'];
                $environment['LARISSAMA_TEST_PAYLOAD'] = json_encode($workerRequest['payload'], JSON_THROW_ON_ERROR);
                $process = new Process(['php', 'tests/Support/concurrent-http-worker.php'], base_path(), $environment);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            $responses = [];

            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(
                    0,
                    $process->getExitCode(),
                    'Worker stdout='.$process->getOutput().'; stderr='.$process->getErrorOutput()
                );
                $response = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($response);
                $responses[] = $response;
            }

            return [
                'responses' => $responses,
                'arrival_count' => count(glob($barrierPrefix.'.*') ?: []),
            ];
        } finally {
            foreach ($processes as $process) {
                $process->stop(1);
            }

            foreach (glob($barrierPrefix.'.*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     */
    private function assertWorkerRequestsOverlap(array $responses): void
    {
        $latestStart = max(array_column($responses, 'request_started_at'));
        $earliestFinish = min(array_column($responses, 'request_finished_at'));

        self::assertLessThan(
            $earliestFinish,
            $latestStart,
            'Interval Kernel::handle untuk dua worker harus beririsan.'
        );
    }

    /**
     * @param  list<string>  $triggerNames
     */
    private function dropDelayedInsertTriggers(array $triggerNames): void
    {
        foreach ($triggerNames as $triggerName) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
        }
    }

    /**
     * @param  list<int|string>  $warungIds
     */
    private function deleteTransactionFixtures(array $warungIds): void
    {
        DB::table('penjualan_rincis')->whereIn('warung_id', $warungIds)->delete();
        DB::table('penjualans')->whereIn('warung_id', $warungIds)->delete();
        DB::table('pembelian_rincis')->whereIn('warung_id', $warungIds)->delete();
        DB::table('pembelians')->whereIn('warung_id', $warungIds)->delete();
    }

    public static function differentPayloadTransactionTypes(): array
    {
        return [
            'sale' => ['penjualan'],
            'purchase' => ['pembelian'],
        ];
    }

    public function beginDatabaseTransaction()
    {
        // Child HTTP Kernel processes need committed fixtures and writes.
    }
}
