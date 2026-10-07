<?php

namespace Tests\Unit\Policies;

use App\Models\KategoriMenu;
use App\Models\User;
use App\Policies\KategoriMenuPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KategoriMenuPolicyTest extends TestCase
{
    #[DataProvider('tenantRoleMatrix')]
    public function test_category_access_is_limited_by_role_and_tenant(
        string $role,
        ?int $warungId,
        bool $canRead,
        bool $canWrite,
    ): void {
        $policy = new KategoriMenuPolicy;
        $actor = $this->user(1, $role, $warungId);
        $ownCategory = $this->category(10);
        $foreignCategory = $this->category(20);

        $this->assertSame($canRead, $policy->viewAny($actor));
        $this->assertSame($canWrite, $policy->create($actor));
        $selectedWarungId = $role === 'superadmin' ? '10' : null;
        $this->assertSame($canRead, $policy->view($actor, $ownCategory, $selectedWarungId));
        if ($role === 'superadmin') {
            $this->assertFalse($policy->view($actor, $ownCategory));
        }
        $this->assertSame($canWrite, $policy->update($actor, $ownCategory));
        $this->assertFalse($policy->view($actor, $foreignCategory));
        $this->assertFalse($policy->update($actor, $foreignCategory));
    }

    /**
     * @return array<string, array{string, ?int, bool, bool}>
     */
    public static function tenantRoleMatrix(): array
    {
        return [
            'owner' => ['owner', 10, true, true],
            'manager' => ['manager', 10, true, true],
            'cashier' => ['kasir', 10, true, false],
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

    private function category(int $warungId): KategoriMenu
    {
        $category = new KategoriMenu;
        $category->setAttribute('warung_id', $warungId);

        return $category;
    }
}
