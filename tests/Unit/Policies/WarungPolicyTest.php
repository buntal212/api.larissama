<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Warung;
use App\Policies\WarungPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WarungPolicyTest extends TestCase
{
    #[DataProvider('roleMatrix')]
    public function test_platform_and_current_warung_permissions_are_separate(
        string $role,
        ?int $warungId,
        bool $canManagePlatform,
        bool $canViewCurrentWarung,
        bool $canUpdateCurrentWarung,
    ): void {
        $policy = new WarungPolicy;
        $actor = $this->user($role, $warungId);
        $warung = new Warung;
        $warung->setAttribute('id', 10);

        $this->assertSame($canManagePlatform, $policy->viewAny($actor));
        $this->assertSame($canManagePlatform, $policy->create($actor));
        $this->assertSame($canManagePlatform, $policy->view($actor, $warung));
        $this->assertSame($canManagePlatform, $policy->update($actor, $warung));
        $this->assertSame($canViewCurrentWarung, $policy->viewCurrent($actor));
        $this->assertSame($canUpdateCurrentWarung, $policy->updateCurrent($actor));
    }

    /**
     * @return array<string, array{string, ?int, bool, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner with tenant' => ['owner', 10, false, true, true],
            'manager with tenant' => ['manager', 10, false, true, false],
            'cashier with tenant' => ['kasir', 10, false, true, false],
            'superadmin without tenant' => ['superadmin', null, true, false, false],
            'superadmin with tenant' => ['superadmin', 10, false, false, false],
            'owner without tenant' => ['owner', null, false, false, false],
            'manager without tenant' => ['manager', null, false, false, false],
            'cashier without tenant' => ['kasir', null, false, false, false],
        ];
    }

    private function user(string $role, ?int $warungId): User
    {
        $user = new User;
        $user->setAttribute('role', $role);
        $user->setAttribute('warung_id', $warungId);

        return $user;
    }
}
