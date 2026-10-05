<?php

namespace Tests\Unit\Policies;

use App\Models\Penjualan;
use App\Models\User;
use App\Policies\PenjualanPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PenjualanPolicyTest extends TestCase
{
    #[DataProvider('roleMatrix')]
    public function test_sale_permissions_follow_owner_manager_and_cashier_roles(
        string $role,
        ?int $warungId,
        bool $canReadAny,
        bool $canCreate,
        bool $canReadOtherCashierSale,
    ): void {
        $policy = new PenjualanPolicy;
        $actor = $this->user(1, $role, $warungId);
        $ownSale = $this->sale(10, 1);
        $otherSale = $this->sale(10, 2);
        $foreignSale = $this->sale(20, 2);

        $this->assertSame($canReadAny, $policy->viewAny($actor));
        $this->assertSame($canCreate, $policy->create($actor));
        $this->assertSame($canReadAny, $policy->view($actor, $ownSale));
        $this->assertSame($canReadOtherCashierSale, $policy->view($actor, $otherSale));
        $this->assertFalse($policy->view($actor, $foreignSale));
        $this->assertFalse($policy->update($actor, $ownSale));
        $this->assertFalse($policy->delete($actor, $ownSale));
        $this->assertFalse($policy->restore($actor, $ownSale));
        $this->assertFalse($policy->forceDelete($actor, $ownSale));
    }

    /**
     * @return array<string, array{string, ?int, bool, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner' => ['owner', 10, true, true, true],
            'manager' => ['manager', 10, true, false, true],
            'cashier' => ['kasir', 10, true, true, false],
            'superadmin' => ['superadmin', null, false, false, false],
            'owner without tenant' => ['owner', null, false, false, false],
            'manager without tenant' => ['manager', null, false, false, false],
            'cashier without tenant' => ['kasir', null, false, false, false],
        ];
    }

    private function user(int $id, string $role, ?int $warungId): User
    {
        $user = new User;
        $user->setAttribute('id', $id);
        $user->setAttribute('role', $role);
        $user->setAttribute('warung_id', $warungId);

        return $user;
    }

    private function sale(int $warungId, int $userId): Penjualan
    {
        $sale = new Penjualan;
        $sale->setAttribute('warung_id', $warungId);
        $sale->setAttribute('user_id', $userId);

        return $sale;
    }
}
