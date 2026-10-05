<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TransactionPeriodMysqlRangeConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_period_bounds_outside_mysql_datetime_range_are_rejected_before_business_queries(): void
    {
        $businessQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$businessQueries): void {
            if (preg_match('/\b(?:penjualans|pembelians)\b/i', $query->sql) === 1) {
                $businessQueries[] = $query->sql;
            }
        });

        $manager = $this->managerForTimezone('Asia/Jakarta');
        $token = $manager->createToken('period-underflow-test')->plainTextToken;

        foreach ($this->operations() as $operation) {
            $businessQueries = [];
            $query = ['date_from' => '1000-01-01', 'date_to' => '1000-01-01'];
            $this->assertOperationQueryMatchesOpenApi($query, $operation['path'], 'get');

            $response = $this->withToken($token)->getJson($operation['url'].'?'.http_build_query($query))
                ->assertUnprocessable();

            $this->assertValidationEnvelope($response, 'date_from');
            $this->assertOperationResponseMatchesOpenApi($response, $operation['path'], 'get');
            $this->assertSame([], $businessQueries, 'An invalid UTC lower bound must be rejected before the business query.');
        }

    }

    public function test_period_upper_bound_outside_mysql_datetime_range_is_rejected_before_business_queries(): void
    {
        $businessQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$businessQueries): void {
            if (preg_match('/\b(?:penjualans|pembelians)\b/i', $query->sql) === 1) {
                $businessQueries[] = $query->sql;
            }
        });

        $manager = $this->managerForTimezone('Etc/GMT+12');
        $token = $manager->createToken('period-overflow-test')->plainTextToken;

        foreach ($this->operations() as $operation) {
            $businessQueries = [];
            $query = ['date_from' => '9999-12-31', 'date_to' => '9999-12-31'];
            $this->assertOperationQueryMatchesOpenApi($query, $operation['path'], 'get');

            $response = $this->withToken($token)->getJson($operation['url'].'?'.http_build_query($query));
            $response->assertUnprocessable();

            $this->assertValidationEnvelope($response, 'date_to');
            $this->assertOperationResponseMatchesOpenApi($response, $operation['path'], 'get');
            $this->assertSame([], $businessQueries, 'An invalid UTC upper bound must be rejected before the business query.');
        }
    }

    public function test_safe_mysql_datetime_period_boundaries_remain_valid_for_all_transaction_reads(): void
    {
        $manager = $this->managerForTimezone('UTC');
        $token = $manager->createToken('period-safe-boundary-test')->plainTextToken;
        $query = ['date_from' => '1000-01-01', 'date_to' => '9999-12-30'];

        foreach ($this->operations() as $operation) {
            $this->assertOperationQueryMatchesOpenApi($query, $operation['path'], 'get');
            $response = $this->withToken($token)->getJson($operation['url'].'?'.http_build_query($query))
                ->assertOk();

            if ($operation['report_total'] === null) {
                $response->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
            } else {
                $response->assertJsonPath('data.jumlah_transaksi', 0)
                    ->assertJsonPath('data.'.$operation['report_total'], '0.00');
            }

            $this->assertOperationResponseMatchesOpenApi($response, $operation['path'], 'get');
        }
    }

    /** @return list<array{path: string, url: string, report_total: ?string}> */
    private function operations(): array
    {
        return [
            ['path' => '/penjualans', 'url' => '/api/v1/penjualans', 'report_total' => null],
            ['path' => '/pembelians', 'url' => '/api/v1/pembelians', 'report_total' => null],
            ['path' => '/laporan/penjualan', 'url' => '/api/v1/laporan/penjualan', 'report_total' => 'total_pendapatan'],
            ['path' => '/laporan/pembelian', 'url' => '/api/v1/laporan/pembelian', 'report_total' => 'total_pembelian'],
        ];
    }

    private function managerForTimezone(string $timezone): User
    {
        $warung = Warung::factory()->create(['timezone' => $timezone]);

        return User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
    }

    private function assertValidationEnvelope(TestResponse $response, string $expectedField): void
    {
        $body = $response->json();
        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame('VALIDATION_ERROR', $body['code']);
        $this->assertArrayHasKey($expectedField, $body['errors']);
        $this->assertNotEmpty($body['errors'][$expectedField]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );
    }
}
