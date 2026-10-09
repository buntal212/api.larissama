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
        bool $canReadTenant,
        bool $canCreate,
        bool $canUpdateSelectedWarung,
    ): void {
        $policy = new UserPolicy;
        $actor = $this->user(1, $role, $warungId);
        $target = $this->user(2, 'manager', 10);

        $this->assertSame($canReadTenant, $policy->viewAny($actor));
        $this->assertSame($canCreate, $policy->create($actor));
        $selectedWarungId = $role === 'superadmin' ? '10' : null;
        $this->assertSame($canReadTenant, $policy->view($actor, $target, $selectedWarungId));
        if ($role === 'superadmin') {
            $this->assertFalse($policy->view($actor, $target));
        }
        $this->assertSame($canUpdateSelectedWarung, $policy->update($actor, $target, $selectedWarungId));
        if ($role === 'superadmin') {
            $this->assertFalse($policy->update($actor, $target));
        }
        $this->assertFalse($policy->view($actor, $this->user(3, 'manager', 20), $selectedWarungId));
        $this->assertFalse($policy->update($actor, $this->user(3, 'manager', 20)));
        $this->assertFalse($policy->view($actor, $this->user(4, 'superadmin', null)));
        $this->assertFalse($policy->update($actor, $this->user(4, 'superadmin', null)));
        $this->assertSame($role === 'owner' && $warungId !== null, $policy->view($actor, $actor, $selectedWarungId));
        $this->assertFalse($policy->update($actor, $actor));
    }

    /**
     * @return array<string, array{string, ?int, bool, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner' => ['owner', 10, true, true, true],
            'manager' => ['manager', 10, false, false, false],
            'cashier' => ['kasir', 10, false, false, false],
            'superadmin' => ['superadmin', null, true, true, true],
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
}
