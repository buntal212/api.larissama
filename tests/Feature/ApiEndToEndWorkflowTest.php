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
        $this->travelTo(CarbonImmutable::parse('2026-10-04T13:00:00Z'));
        $superadminPassword = 'Superadmin-e2e-secret';
        $superadmin = User::factory()->superadmin()->create([
            'username' => 'superadmin-e2e',
            'email' => null,
            'password' => $superadminPassword,
        ]);
        $superadminToken = $this->login($superadmin->username, $superadminPassword);

        $ownerPassword = 'Owner-e2e-secret';
        $provisionPayload = [
            'nama' => 'Warung End to End',
            'timezone' => 'Asia/Jakarta',
            'alamat' => null,
            'telepon' => null,
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

        $approved = $this->withFreshToken($superadminToken)
            ->postJson('/api/v1/admin/warungs/'.$warungId.'/persetujuan')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($approved, '/admin/warungs/{id}/persetujuan', 'post');

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

        $saleId = $saleResponse->json('data.id');
        $saleListQuery = ['page' => '1', 'per_page' => '20'];
        $this->assertOperationQueryMatchesOpenApi($saleListQuery, '/penjualans', 'get');
        $saleList = $this->withFreshToken($managerToken)
            ->getJson('/api/v1/penjualans?'.http_build_query($saleListQuery))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $saleId)
            ->assertJsonPath('data.0.total', '30000.00');
        $this->assertOperationResponseMatchesOpenApi($saleList, '/penjualans', 'get');

        $saleDetail = $this->withFreshToken($managerToken)
            ->getJson("/api/v1/penjualans/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.rincian.0.menu_id', $menuId)
            ->assertJsonPath('data.rincian.0.nama_menu', 'Nasi Goreng')
            ->assertJsonPath('data.rincian.0.harga', '15000.00')
            ->assertJsonPath('data.rincian.0.subtotal', '30000.00');
        $this->assertOperationResponseMatchesOpenApi($saleDetail, '/penjualans/{id}', 'get');

        $summaryPurchaseId = $summaryPurchase->json('data.id');
        $detailedPurchaseId = $detailedPurchase->json('data.id');
        $purchaseListQuery = ['page' => '1', 'per_page' => '20'];
        $this->assertOperationQueryMatchesOpenApi($purchaseListQuery, '/pembelians', 'get');
        $purchaseList = $this->withFreshToken($managerToken)
            ->getJson('/api/v1/pembelians?'.http_build_query($purchaseListQuery))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $detailedPurchaseId)
            ->assertJsonPath('data.1.id', $summaryPurchaseId);
        $this->assertOperationResponseMatchesOpenApi($purchaseList, '/pembelians', 'get');

        $summaryPurchaseDetail = $this->withFreshToken($managerToken)
            ->getJson("/api/v1/pembelians/{$summaryPurchaseId}")
            ->assertOk()
            ->assertJsonPath('data.id', $summaryPurchaseId)
            ->assertJsonPath('data.total', '150000.00')
            ->assertJsonPath('data.rincian.0.nama_item', 'Belanja di pasar')
            ->assertJsonPath('data.rincian.0.qty', null)
            ->assertJsonPath('data.rincian.0.satuan', null)
            ->assertJsonPath('data.rincian.0.harga_satuan', null)
            ->assertJsonPath('data.rincian.0.subtotal', '150000.00');
        $this->assertOperationResponseMatchesOpenApi($summaryPurchaseDetail, '/pembelians/{id}', 'get');

        $detailedPurchaseDetail = $this->withFreshToken($managerToken)
            ->getJson("/api/v1/pembelians/{$detailedPurchaseId}")
            ->assertOk()
            ->assertJsonPath('data.id', $detailedPurchaseId)
            ->assertJsonPath('data.total', '95000.00')
            ->assertJsonCount(2, 'data.rincian')
            ->assertJsonPath('data.rincian.0.nama_item', 'Beras')
            ->assertJsonPath('data.rincian.0.subtotal', '75000.00')
            ->assertJsonPath('data.rincian.1.nama_item', 'Cabai')
            ->assertJsonPath('data.rincian.1.subtotal', '20000.00');
        $this->assertOperationResponseMatchesOpenApi($detailedPurchaseDetail, '/pembelians/{id}', 'get');

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
