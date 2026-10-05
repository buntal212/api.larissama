<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    #[DataProvider('roleMatrix')]
    public function test_only_tenant_owner_can_manage_users(
        string $role,
        ?int $warungId,
        bool $canManage,
    ): void {
        $policy = new UserPolicy;
        $actor = $this->user(1, $role, $warungId);
        $target = $this->user(2, 'manager', 10);

        $this->assertSame($canManage, $policy->viewAny($actor));
        $this->assertSame($canManage, $policy->create($actor));
        $this->assertSame($canManage, $policy->view($actor, $target));
        $this->assertSame($canManage, $policy->update($actor, $target));
        $this->assertFalse($policy->view($actor, $this->user(3, 'manager', 20)));
        $this->assertFalse($policy->update($actor, $this->user(3, 'manager', 20)));
        $this->assertFalse($policy->view($actor, $this->user(4, 'superadmin', null)));
        $this->assertFalse($policy->update($actor, $this->user(4, 'superadmin', null)));
        $this->assertSame($canManage, $policy->view($actor, $actor));
        $this->assertFalse($policy->update($actor, $actor));
    }

    /**
     * @return array<string, array{string, ?int, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner' => ['owner', 10, true],
            'manager' => ['manager', 10, false],
            'cashier' => ['kasir', 10, false],
            'superadmin' => ['superadmin', null, false],
            'owner without tenant' => ['owner', null, false],
            'manager without tenant' => ['manager', null, false],
            'cashier without tenant' => ['kasir', null, false],
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
}
