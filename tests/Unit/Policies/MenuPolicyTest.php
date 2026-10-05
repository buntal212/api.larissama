<?php

namespace Tests\Unit\Policies;

use App\Models\Menu;
use App\Models\User;
use App\Policies\MenuPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MenuPolicyTest extends TestCase
{
    #[DataProvider('tenantRoleMatrix')]
    public function test_menu_access_is_limited_by_role_and_tenant(
        string $role,
        ?int $warungId,
        bool $canRead,
        bool $canWrite,
    ): void {
        $policy = new MenuPolicy;
        $actor = $this->user(1, $role, $warungId);
        $ownMenu = $this->menu(10);
        $foreignMenu = $this->menu(20);

        $this->assertSame($canRead, $policy->viewAny($actor));
        $this->assertSame($canWrite, $policy->create($actor));
        $this->assertSame($canRead, $policy->view($actor, $ownMenu));
        $this->assertSame($canWrite, $policy->update($actor, $ownMenu));
        $this->assertFalse($policy->view($actor, $foreignMenu));
        $this->assertFalse($policy->update($actor, $foreignMenu));
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
            'superadmin' => ['superadmin', null, false, false],
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

    private function menu(int $warungId): Menu
    {
        $menu = new Menu;
        $menu->setAttribute('warung_id', $warungId);

        return $menu;
    }
}
