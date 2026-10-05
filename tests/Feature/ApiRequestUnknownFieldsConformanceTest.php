<?php

namespace Tests\Feature;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Pembelian;
use App\Models\PembelianRinci;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiRequestUnknownFieldsConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_warung_body_operations_reject_unknown_fields_before_writing(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $superadmin = User::factory()->superadmin()->create();
        $superadminToken = $superadmin->createToken('unknown-body-field-superadmin')->plainTextToken;

        $cases = [
            [
                'method' => 'POST',
                'uri' => '/api/v1/admin/warungs',
                'openapi_path' => '/admin/warungs',
                'token' => $superadminToken,
                'body' => [
                    'kode' => 'WRG-UNKNOWN-FIELD',
                    'nama' => 'Warung Baru',
                    'timezone' => 'Asia/Jakarta',
                    'alamat' => null,
                    'telepon' => null,
                    'tanggal_mulai' => null,
                    'tanggal_berakhir' => null,
                    'owner' => [
                        'nama' => 'Owner Baru',
                        'username' => 'owner-unknown-field',
                        'email' => null,
                        'password' => 'owner-password-123',
                    ],
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/v1/admin/warungs/'.$warung->id,
                'openapi_path' => '/admin/warungs/{id}',
                'token' => $superadminToken,
                'body' => ['nama' => 'Nama Tidak Boleh Berubah'],
            ],
        ];

        $this->assertCasesRejectUnknownFields($cases);

        $this->assertDatabaseCount('warungs', 1);
        $this->assertDatabaseHas('warungs', ['id' => $warung->id, 'nama' => $warung->nama]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_tenant_body_operations_reject_unknown_fields_before_writing(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $targetUser = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'kasir',
            'nama' => 'Kasir Tetap',
        ]);
        $category = KategoriMenu::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Kategori Tetap',
        ]);
        $menu = Menu::factory()->create([
            'warung_id' => $warung->id,
            'kategori_menu_id' => $category->id,
            'kode' => 'MENU-TETAP',
            'nama' => 'Menu Tetap',
            'harga' => '5000.00',
        ]);
        $ownerToken = $owner->createToken('unknown-body-field-owner')->plainTextToken;

        $cases = [
            [
                'method' => 'POST',
                'uri' => '/api/v1/users',
                'openapi_path' => '/users',
                'token' => $ownerToken,
                'body' => [
                    'nama' => 'User Baru',
                    'username' => 'user-unknown-field',
                    'password' => 'user-password-123',
                    'role' => 'manager',
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/v1/users/'.$targetUser->id,
                'openapi_path' => '/users/{id}',
                'token' => $ownerToken,
                'body' => ['nama' => 'Nama Kasir Tidak Boleh Berubah'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/kategori-menus',
                'openapi_path' => '/kategori-menus',
                'token' => $ownerToken,
                'body' => ['nama' => 'Kategori Baru'],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/v1/kategori-menus/'.$category->id,
                'openapi_path' => '/kategori-menus/{id}',
                'token' => $ownerToken,
                'body' => ['nama' => 'Kategori Tidak Boleh Berubah'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/menus',
                'openapi_path' => '/menus',
                'token' => $ownerToken,
                'body' => [
                    'kategori_menu_id' => (string) $category->id,
                    'kode' => 'MENU-BARU',
                    'nama' => 'Menu Baru',
                    'harga' => '7000.00',
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/v1/menus/'.$menu->id,
                'openapi_path' => '/menus/{id}',
                'token' => $ownerToken,
                'body' => ['nama' => 'Menu Tidak Boleh Berubah'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/penjualans',
                'openapi_path' => '/penjualans',
                'token' => $ownerToken,
                'headers' => ['Idempotency-Key' => 'unknown-field-sale-001'],
                'body' => [
                    'tanggal' => '2026-10-05T12:00:00+07:00',
                    'bayar' => '5000.00',
                    'metode_pembayaran' => 'cash',
                    'rincian' => [['menu_id' => (string) $menu->id, 'qty' => '1.00']],
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/pembelians',
                'openapi_path' => '/pembelians',
                'token' => $ownerToken,
                'headers' => ['Idempotency-Key' => 'unknown-field-purchase-001'],
                'body' => [
                    'tanggal' => '2026-10-05T12:00:00+07:00',
                    'rincian' => [['nama_item' => 'Belanja pasar', 'subtotal' => '10000.00']],
                ],
            ],
        ];

        $this->assertCasesRejectUnknownFields($cases);

        $this->assertDatabaseCount('warungs', 1);
        $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'nama' => 'Kasir Tetap']);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('kategori_menus', 1);
        $this->assertDatabaseHas('kategori_menus', ['id' => $category->id, 'nama' => 'Kategori Tetap']);
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'nama' => 'Menu Tetap', 'harga' => '5000.00']);
        $this->assertDatabaseCount('penjualans', 0);
        $this->assertDatabaseCount('penjualan_rincis', 0);
        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_rincis', 0);
    }

    public function test_purchase_correction_operations_reject_unknown_fields_without_mutating_history(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $purchase = Pembelian::factory()->create([
            'warung_id' => $warung->id,
            'user_id' => $owner->id,
            'total' => '125.00',
            'catatan' => 'Catatan awal',
        ]);
        PembelianRinci::factory()->create([
            'warung_id' => $warung->id,
            'pembelian_id' => $purchase->id,
            'nama_item' => 'Beras',
            'subtotal' => '125.00',
        ]);
        $ownerToken = $owner->createToken('unknown-field-purchase-correction')->plainTextToken;

        $cases = [
            [
                'method' => 'PATCH',
                'uri' => '/api/v1/pembelians/'.$purchase->id,
                'openapi_path' => '/pembelians/{id}',
                'headers' => ['Idempotency-Key' => 'unknown-field-purchase-update'],
                'body' => ['alasan' => 'Perbarui catatan.', 'catatan' => 'Nilai tetap'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/pembelians/'.$purchase->id.'/pembatalan',
                'openapi_path' => '/pembelians/{id}/pembatalan',
                'headers' => ['Idempotency-Key' => 'unknown-field-purchase-cancel'],
                'body' => ['alasan' => 'Batalkan duplikat.'],
            ],
        ];

        foreach ($cases as $case) {
            $body = [...$case['body'], 'warung_id' => (string) $warung->id];
            $method = strtolower($case['method']);
            $this->assertOperationRequestDoesNotMatchOpenApi($body, $case['openapi_path'], $method);

            $response = $this->withToken($ownerToken)->json(
                $case['method'],
                $case['uri'],
                $body,
                $case['headers'],
            )->assertUnprocessable();

            $this->assertOperationResponseMatchesOpenApi($response, $case['openapi_path'], $method);
            $response->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('warung_id');
        }

        $this->assertDatabaseHas('pembelians', [
            'id' => $purchase->id,
            'total' => '125.00',
            'catatan' => 'Catatan awal',
            'status' => 'tercatat',
        ]);
        $this->assertSame(1, PembelianRinci::query()->where('pembelian_id', $purchase->id)->count());
        $this->assertSame(0, DB::table('pembelian_koreksis')->count());
    }

    /**
     * @param  array<int, array{method: string, uri: string, openapi_path: string, token: string, body: array<string, mixed>, headers?: array<string, string>}>  $cases
     */
    private function assertCasesRejectUnknownFields(array $cases): void
    {
        foreach ($cases as $case) {
            $body = [...$case['body'], 'unexpected_field' => 'must be rejected'];
            $method = strtolower($case['method']);
            $this->assertOperationRequestDoesNotMatchOpenApi($body, $case['openapi_path'], $method);
            $request = $this->withoutHeader('Authorization')->withToken($case['token']);

            $response = match ($case['method']) {
                'POST' => $request->postJson(
                    $case['uri'],
                    $body,
                    $case['headers'] ?? [],
                ),
                'PATCH' => $request->patchJson(
                    $case['uri'],
                    $body,
                    $case['headers'] ?? [],
                ),
            };
            $this->assertSame(
                422,
                $response->status(),
                "Unexpected status for {$case['method']} {$case['uri']}: {$response->getContent()}",
            );

            $this->assertOperationResponseMatchesOpenApi($response, $case['openapi_path'], $method);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertArrayHasKey('unexpected_field', $response->json('errors'));
        }
    }
}
