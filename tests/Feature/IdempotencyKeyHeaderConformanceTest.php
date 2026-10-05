<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\Penjualan;
use App\Models\PenjualanRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdempotencyKeyHeaderConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_and_purchase_enforce_the_openapi_idempotency_key_bounds(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $category = KategoriMenu::factory()->create(['warung_id' => $warung->id]);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'harga' => '100.00',
        ]);
        $token = $owner->createToken('idempotency-key-header-conformance')->plainTextToken;

        $operations = [
            [
                'path' => '/penjualans',
                'payload' => [
                    'tanggal' => '2026-10-04T12:00:00Z',
                    'bayar' => '100.00',
                    'metode_pembayaran' => 'cash',
                    'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
                ],
                'header_table' => 'penjualans',
                'detail_table' => 'penjualan_rincis',
                'header_model' => Penjualan::class,
                'detail_model' => PenjualanRinci::class,
                'key_field' => 'idempotency_key',
            ],
            [
                'path' => '/pembelians',
                'payload' => [
                    'tanggal' => '2026-10-04T12:00:00Z',
                    'rincian' => [['nama_item' => 'Bahan uji', 'subtotal' => '25.00']],
                ],
                'header_table' => 'pembelians',
                'detail_table' => 'pembelian_rincis',
                'header_model' => Pembelian::class,
                'detail_model' => PembelianRinci::class,
                'key_field' => 'idempotency_key',
            ],
        ];

        foreach ($operations as $operation) {
            $contractPath = $operation['path'];
            $url = '/api/v1'.$contractPath;
            $headerSchema = $this->requiredIdempotencyHeaderSchema($contractPath);
            $baseline = $this->transactionCounts($operation);

            $invalidHeaders = [
                'missing' => [],
                'empty' => ['Idempotency-Key' => ''],
                'too long' => ['Idempotency-Key' => str_repeat('k', 256)],
                'leading whitespace' => ['Idempotency-Key' => ' key'],
                'trailing whitespace' => ['Idempotency-Key' => 'key '],
            ];

            foreach ($invalidHeaders as $case => $headers) {
                if ($case === 'missing') {
                    $this->assertTrue($headerSchema['required']);
                } else {
                    $value = $headers['Idempotency-Key'];
                    $schemaErrors = $this->collectOpenApiSchemaErrors(
                        $value,
                        $headerSchema['schema'],
                        $this->openApiDocument(),
                        'header.Idempotency-Key',
                    );
                    $this->assertNotEmpty($schemaErrors, "OpenAPI should reject the {$case} idempotency key.");
                }

                $response = $this->withToken($token)->postJson($url, $operation['payload'], $headers);
                $response->assertUnprocessable();
                $this->assertSame('VALIDATION_ERROR', $response->json('code'));
                $this->assertArrayHasKey('Idempotency-Key', $response->json('errors'));
                $this->assertOperationResponseMatchesOpenApi($response, $contractPath, 'post');
                $this->assertSame($baseline, $this->transactionCounts($operation));
            }

            $validBoundaryKey = str_repeat('k', 127).' '.str_repeat('k', 127);
            $this->assertOperationRequestMatchesOpenApi(
                $operation['payload'],
                ['Idempotency-Key' => $validBoundaryKey],
                $contractPath,
                'post',
            );
            $created = $this->withToken($token)
                ->postJson($url, $operation['payload'], ['Idempotency-Key' => $validBoundaryKey])
                ->assertCreated();
            $this->assertOperationResponseMatchesOpenApi($created, $contractPath, 'post');
            $this->assertSame(1, $operation['header_model']::query()->count());
            $this->assertSame(1, $operation['detail_model']::query()->count());
            $this->assertDatabaseHas($operation['header_table'], [
                $operation['key_field'] => $validBoundaryKey,
                'warung_id' => $warung->id,
                'user_id' => $owner->id,
            ]);
        }
    }

    public function test_purchase_correction_operations_enforce_the_openapi_idempotency_key_bounds(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('idempotency-key-purchase-correction')->plainTextToken;
        $operations = [
            [
                'path' => '/pembelians/{id}',
                'method' => 'PATCH',
                'action' => 'update',
                'payload' => ['alasan' => 'Koreksi catatan.', 'catatan' => 'Catatan diperbaiki.'],
            ],
            [
                'path' => '/pembelians/{id}/pembatalan',
                'method' => 'POST',
                'action' => 'cancel',
                'payload' => ['alasan' => 'Transaksi duplikat.'],
            ],
            [
                'path' => '/penjualans/{id}',
                'method' => 'PATCH',
                'action' => 'sale-update',
                'payload' => ['alasan' => 'Koreksi catatan.', 'catatan' => 'Catatan diperbaiki.'],
            ],
            [
                'path' => '/penjualans/{id}/pembatalan',
                'method' => 'POST',
                'action' => 'sale-cancel',
                'payload' => ['alasan' => 'Transaksi salah input.'],
            ],
            [
                'path' => '/penjualans/{id}/retur',
                'method' => 'POST',
                'action' => 'sale-return',
                'payload' => ['nominal' => '1.00', 'alasan' => 'Pengembalian barang.'],
            ],
        ];

        foreach ($operations as $index => $operation) {
            $purchase = null;
            $sale = null;
            if (str_starts_with($operation['action'], 'sale-')) {
                $sale = Penjualan::factory()->create(['warung_id' => $warung->id, 'user_id' => $owner->id]);
                $target = $sale;
            } else {
                $purchase = Pembelian::factory()->create(['warung_id' => $warung->id, 'user_id' => $owner->id]);
                $target = $purchase;
            }
            $url = '/api/v1'.str_replace('/{id}', '/'.$target->id, $operation['path']);
            $headerSchema = $this->requiredIdempotencyHeaderSchema($operation['path'], strtolower($operation['method']));

            $invalidHeaders = [
                'missing' => [],
                'empty' => ['Idempotency-Key' => ''],
                'too long' => ['Idempotency-Key' => str_repeat('k', 256)],
                'leading whitespace' => ['Idempotency-Key' => ' key'],
                'trailing whitespace' => ['Idempotency-Key' => 'key '],
            ];

            foreach ($invalidHeaders as $case => $headers) {
                if ($case === 'missing') {
                    $this->assertTrue($headerSchema['required']);
                } else {
                    $schemaErrors = $this->collectOpenApiSchemaErrors(
                        $headers['Idempotency-Key'],
                        $headerSchema['schema'],
                        $this->openApiDocument(),
                        'header.Idempotency-Key',
                    );
                    $this->assertNotEmpty($schemaErrors, "OpenAPI should reject the {$case} correction key.");
                }

                $response = $this->withToken($token)->json($operation['method'], $url, $operation['payload'], $headers)
                    ->assertUnprocessable()
                    ->assertJsonPath('code', 'VALIDATION_ERROR')
                    ->assertJsonValidationErrors('Idempotency-Key');

                $this->assertOperationResponseMatchesOpenApi($response, $operation['path'], strtolower($operation['method']));
                if ($purchase instanceof Pembelian) {
                    $this->assertSame('tercatat', $purchase->fresh()->status);
                    $this->assertSame(0, DB::table('pembelian_koreksis')->where('pembelian_id', $purchase->id)->count());
                } else {
                    $this->assertInstanceOf(Penjualan::class, $sale);
                    $this->assertSame('selesai', $sale->fresh()->status);
                    $this->assertSame(0, DB::table('penjualan_koreksis')->where('penjualan_id', $sale->id)->count());
                    $this->assertSame(0, DB::table('penjualan_returs')->where('penjualan_id', $sale->id)->count());
                }
            }

            $boundaryKey = str_repeat(chr(97 + $index), 255);
            $this->assertOperationRequestMatchesOpenApi(
                $operation['payload'],
                ['Idempotency-Key' => $boundaryKey],
                $operation['path'],
                strtolower($operation['method']),
            );

            $created = $this->withToken($token)->json(
                $operation['method'],
                $url,
                $operation['payload'],
                ['Idempotency-Key' => $boundaryKey],
            )->assertCreated();

            $this->assertOperationResponseMatchesOpenApi($created, $operation['path'], strtolower($operation['method']));
            if ($purchase instanceof Pembelian) {
                $this->assertDatabaseHas('pembelian_koreksis', [
                    'pembelian_id' => $purchase->id,
                    'user_id' => $owner->id,
                    'idempotency_key' => $boundaryKey,
                ]);
                $this->assertSame($operation['action'] === 'cancel' ? 'dibatalkan' : 'tercatat', $purchase->fresh()->status);
            } elseif ($operation['action'] === 'sale-return') {
                $this->assertInstanceOf(Penjualan::class, $sale);
                $this->assertDatabaseHas('penjualan_returs', [
                    'penjualan_id' => $sale->id,
                    'user_id' => $owner->id,
                    'idempotency_key' => $boundaryKey,
                ]);
            } else {
                $this->assertInstanceOf(Penjualan::class, $sale);
                $this->assertDatabaseHas('penjualan_koreksis', [
                    'penjualan_id' => $sale->id,
                    'user_id' => $owner->id,
                    'idempotency_key' => $boundaryKey,
                ]);
                $this->assertSame($operation['action'] === 'sale-cancel' ? 'batal' : 'selesai', $sale->fresh()->status);
            }
        }
    }

    /** @return array{required: bool, schema: array<string, mixed>} */
    private function requiredIdempotencyHeaderSchema(string $path, string $method = 'post'): array
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][$method] ?? null;
        $this->assertIsArray($operation);

        foreach ($operation['parameters'] ?? [] as $parameterDefinition) {
            $parameter = isset($parameterDefinition['$ref'])
                ? $this->resolveOpenApiReference($document, $parameterDefinition['$ref'])
                : $parameterDefinition;
            if (($parameter['in'] ?? null) === 'header' && ($parameter['name'] ?? null) === 'Idempotency-Key') {
                $this->assertSame('string', $parameter['schema']['type'] ?? null);
                $this->assertSame(1, $parameter['schema']['minLength'] ?? null);
                $this->assertSame(255, $parameter['schema']['maxLength'] ?? null);

                return [
                    'required' => (bool) ($parameter['required'] ?? false),
                    'schema' => $parameter['schema'],
                ];
            }
        }

        $this->fail("OpenAPI {$path} ".strtoupper($method).' must declare Idempotency-Key.');
    }

    /** @param array<string, mixed> $operation @return array{headers: int, details: int} */
    private function transactionCounts(array $operation): array
    {
        return [
            'headers' => $operation['header_model']::query()->count(),
            'details' => $operation['detail_model']::query()->count(),
        ];
    }
}
