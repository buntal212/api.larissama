<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiEndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_can_be_provisioned_used_for_sales_purchases_and_reports_then_logout(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04T03:00:00Z'));
        $superadminPassword = 'Superadmin-e2e-secret';
        $superadmin = User::factory()->superadmin()->create([
            'username' => 'superadmin-e2e',
            'email' => null,
            'password' => $superadminPassword,
        ]);
        $superadminToken = $this->login($superadmin->username, $superadminPassword);

        $ownerPassword = 'Owner-e2e-secret';
        $provisionPayload = [
            'kode' => 'WRG-E2E-001',
            'nama' => 'Warung End to End',
            'timezone' => 'Asia/Jakarta',
            'alamat' => null,
            'telepon' => null,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
            'owner' => [
                'nama' => 'Owner E2E',
                'username' => 'owner-e2e',
                'email' => null,
                'password' => $ownerPassword,
            ],
        ];
        $this->assertOperationRequestMatchesOpenApi($provisionPayload, [], '/admin/warungs', 'post');
        $provisioned = $this->withFreshToken($superadminToken)
            ->postJson('/api/v1/admin/warungs', $provisionPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($provisioned, '/admin/warungs', 'post');
        $warungId = $provisioned->json('data.warung.id');
        $ownerId = $provisioned->json('data.owner.id');
        $this->assertSame($warungId, $provisioned->json('data.owner.warung_id'));

        $ownerToken = $this->login('owner-e2e', $ownerPassword);
        $managerPassword = 'Manager-e2e-secret';
        $managerPayload = [
            'nama' => 'Manager E2E',
            'username' => 'manager-e2e',
            'email' => null,
            'password' => $managerPassword,
            'role' => 'manager',
        ];
        $this->assertOperationRequestMatchesOpenApi($managerPayload, [], '/users', 'post');
        $managerResponse = $this->withFreshToken($ownerToken)
            ->postJson('/api/v1/users', $managerPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($managerResponse, '/users', 'post');

        $cashierPassword = 'Cashier-e2e-secret';
        $cashierPayload = [
            'nama' => 'Kasir E2E',
            'username' => 'cashier-e2e',
            'email' => null,
            'password' => $cashierPassword,
            'role' => 'kasir',
        ];
        $this->assertOperationRequestMatchesOpenApi($cashierPayload, [], '/users', 'post');
        $cashierResponse = $this->withFreshToken($ownerToken)
            ->postJson('/api/v1/users', $cashierPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($cashierResponse, '/users', 'post');
        $managerId = $managerResponse->json('data.id');
        $cashierId = $cashierResponse->json('data.id');
        $this->assertSame($warungId, $managerResponse->json('data.warung_id'));
        $this->assertSame($warungId, $cashierResponse->json('data.warung_id'));

        $managerToken = $this->login('manager-e2e', $managerPassword);
        $cashierToken = $this->login('cashier-e2e', $cashierPassword);
        $cashierIdentity = $this->withFreshToken($cashierToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($cashierIdentity, '/auth/me', 'get');
        $this->assertSame($cashierId, $cashierIdentity->json('data.user.id'));

        $categoryPayload = ['nama' => 'Makanan', 'urutan' => 1];
        $this->assertOperationRequestMatchesOpenApi($categoryPayload, [], '/kategori-menus', 'post');
        $categoryResponse = $this->withFreshToken($managerToken)
            ->postJson('/api/v1/kategori-menus', $categoryPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($categoryResponse, '/kategori-menus', 'post');

        $menuPayload = [
            'kategori_menu_id' => $categoryResponse->json('data.id'),
            'kode' => 'M-NASI-E2E',
            'nama' => 'Nasi Goreng',
            'harga' => '15000.00',
            'deskripsi' => null,
        ];
        $this->assertOperationRequestMatchesOpenApi($menuPayload, [], '/menus', 'post');
        $menuResponse = $this->withFreshToken($managerToken)
            ->postJson('/api/v1/menus', $menuPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($menuResponse, '/menus', 'post');
        $menuId = $menuResponse->json('data.id');
        $this->assertSame($warungId, $categoryResponse->json('data.warung_id'));
        $this->assertSame($warungId, $menuResponse->json('data.warung_id'));

        $salePayload = [
            'tanggal' => '2026-10-04T10:00:00+07:00',
            'bayar' => '30000.00',
            'metode_pembayaran' => 'cash',
            'rincian' => [['menu_id' => $menuId, 'qty' => '2.00']],
        ];
        $saleHeaders = ['Idempotency-Key' => 'e2e-sale-001'];
        $this->assertOperationRequestMatchesOpenApi($salePayload, $saleHeaders, '/penjualans', 'post');
        $saleResponse = $this->withFreshToken($cashierToken)
            ->postJson('/api/v1/penjualans', $salePayload, $saleHeaders)
            ->assertCreated()
            ->assertJsonPath('data.warung_id', $warungId)
            ->assertJsonPath('data.user_id', $cashierId)
            ->assertJsonPath('data.total', '30000.00');
        $this->assertOperationResponseMatchesOpenApi($saleResponse, '/penjualans', 'post');

        $summaryPurchasePayload = [
            'tanggal' => '2026-10-04T11:00:00+07:00',
            'rincian' => [['nama_item' => 'Belanja di pasar', 'subtotal' => '150000.00']],
        ];
        $summaryPurchaseHeaders = ['Idempotency-Key' => 'e2e-purchase-summary-001'];
        $this->assertOperationRequestMatchesOpenApi(
            $summaryPurchasePayload,
            $summaryPurchaseHeaders,
            '/pembelians',
            'post',
        );
        $summaryPurchase = $this->withFreshToken($managerToken)
            ->postJson('/api/v1/pembelians', $summaryPurchasePayload, $summaryPurchaseHeaders)
            ->assertCreated()
            ->assertJsonPath('data.warung_id', $warungId)
            ->assertJsonPath('data.user_id', $managerId)
            ->assertJsonPath('data.total', '150000.00');
        $this->assertOperationResponseMatchesOpenApi($summaryPurchase, '/pembelians', 'post');

        $detailedPurchasePayload = [
            'tanggal' => '2026-10-04T12:00:00+07:00',
            'rincian' => [
                ['nama_item' => 'Beras', 'qty' => '5.00', 'satuan' => 'kg', 'harga_satuan' => '15000.00'],
                ['nama_item' => 'Cabai', 'qty' => '0.50', 'satuan' => 'kg', 'harga_satuan' => '40000.00'],
            ],
        ];
        $detailedPurchaseHeaders = ['Idempotency-Key' => 'e2e-purchase-detailed-001'];
        $this->assertOperationRequestMatchesOpenApi(
            $detailedPurchasePayload,
            $detailedPurchaseHeaders,
            '/pembelians',
            'post',
        );
        $detailedPurchase = $this->withFreshToken($managerToken)
            ->postJson('/api/v1/pembelians', $detailedPurchasePayload, $detailedPurchaseHeaders)
            ->assertCreated()
            ->assertJsonPath('data.warung_id', $warungId)
            ->assertJsonPath('data.user_id', $managerId)
            ->assertJsonPath('data.total', '95000.00');
        $this->assertOperationResponseMatchesOpenApi($detailedPurchase, '/pembelians', 'post');

        $reportQuery = ['date_from' => '2026-10-04', 'date_to' => '2026-10-04'];
        $this->assertOperationQueryMatchesOpenApi($reportQuery, '/laporan/penjualan', 'get');
        $salesReport = $this->withFreshToken($managerToken)
            ->getJson('/api/v1/laporan/penjualan?'.http_build_query($reportQuery))
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 1)
            ->assertJsonPath('data.total_pendapatan', '30000.00')
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
        $this->assertOperationResponseMatchesOpenApi($salesReport, '/laporan/penjualan', 'get');

        $this->assertOperationQueryMatchesOpenApi($reportQuery, '/laporan/pembelian', 'get');
        $purchaseReport = $this->withFreshToken($managerToken)
            ->getJson('/api/v1/laporan/pembelian?'.http_build_query($reportQuery))
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 2)
            ->assertJsonPath('data.total_pembelian', '245000.00')
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
        $this->assertOperationResponseMatchesOpenApi($purchaseReport, '/laporan/pembelian', 'get');

        $logout = $this->withFreshToken($cashierToken)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertOperationResponseMatchesOpenApi($logout, '/auth/logout', 'post');
        $this->assertSame('', $logout->getContent());
        $expiredSession = $this->withFreshToken($cashierToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertOperationResponseMatchesOpenApi($expiredSession, '/auth/me', 'get');
    }

    private function login(string $username, string $password): string
    {
        Auth::forgetGuards();
        $payload = ['username' => $username, 'password' => $password];
        $this->assertOperationRequestMatchesOpenApi($payload, [], '/auth/login', 'post');
        $response = $this->postJson('/api/v1/auth/login', $payload)->assertOk();
        $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');

        return (string) $response->json('data.access_token');
    }

    private function withFreshToken(string $token): static
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
