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
    }

    /**
     * @return array<string, array{string, ?int, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner with tenant' => ['owner', 10, false, true],
            'manager with tenant' => ['manager', 10, false, true],
            'cashier with tenant' => ['kasir', 10, false, true],
            'superadmin without tenant' => ['superadmin', null, true, false],
            'superadmin with tenant' => ['superadmin', 10, false, false],
            'owner without tenant' => ['owner', null, false, false],
            'manager without tenant' => ['manager', null, false, false],
            'cashier without tenant' => ['kasir', null, false, false],
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
