<?php

namespace Tests\Unit\Policies;

use App\Models\Pembelian;
use App\Models\User;
use App\Policies\PembelianPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PembelianPolicyTest extends TestCase
{
    #[DataProvider('roleMatrix')]
    public function test_purchase_permissions_follow_owner_manager_and_cashier_roles(
        string $role,
        ?int $warungId,
        bool $canRead,
        bool $canManage,
    ): void {
        $policy = new PembelianPolicy;
        $actor = $this->user(1, $role, $warungId);
        $ownPurchase = $this->purchase(10);
        $foreignPurchase = $this->purchase(20);

        $this->assertSame($canRead, $policy->viewAny($actor));
        $this->assertSame($canManage, $policy->create($actor));
        $selectedWarungId = $role === 'superadmin' ? '10' : null;
        $this->assertSame($canRead, $policy->view($actor, $ownPurchase, $selectedWarungId));
        if ($role === 'superadmin') {
            $this->assertFalse($policy->view($actor, $ownPurchase));
        }
        $this->assertFalse($policy->view($actor, $foreignPurchase, $selectedWarungId));
        $this->assertSame($canManage, $policy->update($actor, $ownPurchase));
        $this->assertSame($canManage, $policy->cancel($actor, $ownPurchase));
        $this->assertFalse($policy->update($actor, $foreignPurchase));
        $this->assertFalse($policy->cancel($actor, $foreignPurchase));
        $this->assertFalse($policy->delete($actor, $ownPurchase));
        $this->assertFalse($policy->restore($actor, $ownPurchase));
        $this->assertFalse($policy->forceDelete($actor, $ownPurchase));
    }

    /**
     * @return array<string, array{string, ?int, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner' => ['owner', 10, true, true],
            'manager' => ['manager', 10, true, true],
            'cashier' => ['kasir', 10, false, false],
            'superadmin' => ['superadmin', null, true, false],
            'owner without tenant' => ['owner', null, false, false],
            'manager without tenant' => ['manager', null, false, false],
            'cashier without tenant' => ['kasir', null, false, false],
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

    private function purchase(int $warungId): Pembelian
    {
        $purchase = new Pembelian;
        $purchase->setAttribute('warung_id', $warungId);

        return $purchase;
    }
}
