<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_show_and_update_users_in_their_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $ownerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $ownerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'owner']);
        $managerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'manager']);
        $token = $ownerA->createToken('user-feature-test')->plainTextToken;
        $password = 'manager-test-password';
        $createPayload = [
            'nama' => 'Manager Warung A',
            'username' => 'manager-warung-a',
            'email' => null,
            'password' => $password,
            'role' => 'manager',
        ];
        $this->assertOperationRequestMatchesOpenApi($createPayload, [], '/users', 'post');

        $created = $this->withToken($token)
            ->postJson('/api/v1/users', $createPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/users', 'post');

        $userResource = $created->json('data');
        $this->assertEqualsCanonicalizing(
            ['id', 'warung_id', 'nama', 'username', 'email', 'role', 'aktif', 'created_at', 'updated_at'],
            array_keys($userResource),
        );
        $this->assertSame((string) $warungA->id, $userResource['warung_id']);
        $this->assertSame('manager', $userResource['role']);
        $this->assertTrue($userResource['aktif']);
        $this->assertArrayNotHasKey('password', $userResource);
        $this->assertStringNotContainsString($password, $created->getContent());
        $this->assertDatabaseHas('users', [
            'id' => (int) $userResource['id'],
            'warung_id' => $warungA->id,
            'username' => 'manager-warung-a',
            'role' => 'manager',
            'aktif' => true,
        ]);

        $createdUser = User::query()->findOrFail((int) $userResource['id']);
        $this->assertTrue(Hash::check($password, $createdUser->password));

        $listQuery = ['page' => '1', 'per_page' => '20', 'sort' => 'nama'];
        $this->assertOperationQueryMatchesOpenApi($listQuery, '/users', 'get');
        $list = $this->withToken($token)
            ->getJson('/api/v1/users?'.http_build_query($listQuery))
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/users', 'get');
        $listedIds = array_column($list->json('data'), 'id');
        $this->assertSame(2, $list->json('meta.total'));
        $this->assertContains((string) $ownerA->id, $listedIds);
        $this->assertContains((string) $createdUser->id, $listedIds);
        $this->assertNotContains((string) $ownerB->id, $listedIds);
        $this->assertNotContains((string) $managerB->id, $listedIds);

        $detail = $this->withToken($token)
            ->getJson('/api/v1/users/'.$createdUser->id)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/users/{id}', 'get');
        $this->assertSame((string) $createdUser->id, $detail->json('data.id'));
        $this->assertSame('manager', $detail->json('data.role'));

        $updatePayload = [
            'nama' => 'Kasir Warung A',
            'role' => 'kasir',
        ];
        $this->assertOperationRequestMatchesOpenApi($updatePayload, [], '/users/{id}', 'patch');

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$createdUser->id, $updatePayload)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/users/{id}', 'patch');

        $this->assertSame('Kasir Warung A', $updated->json('data.nama'));
        $this->assertSame('kasir', $updated->json('data.role'));
        $this->assertSame((string) $warungA->id, $updated->json('data.warung_id'));
        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'warung_id' => $warungA->id,
            'nama' => 'Kasir Warung A',
            'role' => 'kasir',
        ]);
    }

    public function test_account_identifiers_require_lowercase_and_allow_digits(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('lowercase-account-test')->plainTextToken;
        $basePayload = [
            'nama' => 'Kasir Baru',
            'password' => 'lowercase-test-password',
            'role' => 'kasir',
        ];

        $uppercaseUsernamePayload = [...$basePayload, 'username' => 'Kasir123'];
        $this->assertOperationRequestDoesNotMatchOpenApi($uppercaseUsernamePayload, '/users', 'post');
        $uppercaseUsername = $this->withToken($token)
            ->postJson('/api/v1/users', $uppercaseUsernamePayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($uppercaseUsername, '/users', 'post');
        $this->assertArrayHasKey('username', $uppercaseUsername->json('errors'));

        $uppercaseEmailPayload = [...$basePayload, 'username' => 'kasir123', 'email' => 'Kasir123@example.com'];
        $this->assertOperationRequestDoesNotMatchOpenApi($uppercaseEmailPayload, '/users', 'post');
        $uppercaseEmail = $this->withToken($token)
            ->postJson('/api/v1/users', $uppercaseEmailPayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($uppercaseEmail, '/users', 'post');
        $this->assertArrayHasKey('email', $uppercaseEmail->json('errors'));
        $this->assertDatabaseCount('users', 1);

        $payload = [...$basePayload, 'username' => 'kasir123', 'email' => 'kasir123@example.com'];
        $this->assertOperationRequestMatchesOpenApi($payload, [], '/users', 'post');
        $created = $this->withToken($token)->postJson('/api/v1/users', $payload)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/users', 'post');
        $createdUser = User::query()->findOrFail((int) $created->json('data.id'));

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'username' => 'kasir123',
            'email' => 'kasir123@example.com',
        ]);

        $uppercaseEmailUpdatePayload = ['email' => 'Kasir123@example.com'];
        $this->assertOperationRequestDoesNotMatchOpenApi($uppercaseEmailUpdatePayload, '/users/{id}', 'patch');
        $uppercaseEmailUpdate = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$createdUser->id, $uppercaseEmailUpdatePayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($uppercaseEmailUpdate, '/users/{id}', 'patch');
        $this->assertArrayHasKey('email', $uppercaseEmailUpdate->json('errors'));
        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'email' => 'kasir123@example.com',
        ]);

        $uppercaseLoginPayload = ['username' => 'Kasir123', 'password' => 'lowercase-test-password'];
        $this->assertOperationRequestDoesNotMatchOpenApi($uppercaseLoginPayload, '/auth/login', 'post');
        $uppercaseLogin = $this->postJson('/api/v1/auth/login', $uppercaseLoginPayload)->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($uppercaseLogin, '/auth/login', 'post');
        $this->assertArrayHasKey('username', $uppercaseLogin->json('errors'));
        $this->assertSame(0, $createdUser->tokens()->count());

        $lowercaseLogin = $this->postJson('/api/v1/auth/login', [
            'username' => 'kasir123',
            'password' => 'lowercase-test-password',
        ])->assertOk();
        $this->assertOperationResponseMatchesOpenApi($lowercaseLogin, '/auth/login', 'post');
    }

    public function test_owner_can_delegate_owner_role_within_their_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $ownerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $ownerB = User::factory()->create(['warung_id' => $warungB->id, 'role' => 'owner']);
        $token = $ownerA->createToken('user-feature-test')->plainTextToken;
        $payload = [
            'nama' => 'Owner Kedua',
            'username' => 'owner-kedua-warung-a',
            'password' => 'owner-delegation-password',
            'role' => 'owner',
        ];

        $this->assertOperationRequestMatchesOpenApi($payload, [], '/users', 'post');
        $created = $this->withToken($token)->postJson('/api/v1/users', $payload)->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($created, '/users', 'post');
        $delegatedOwnerId = $created->json('data.id');
        $this->assertSame('owner', $created->json('data.role'));
        $this->assertSame((string) $warungA->id, $created->json('data.warung_id'));

        $delegatedOwner = User::query()->findOrFail((int) $delegatedOwnerId);
        $delegatedToken = $delegatedOwner->createToken('delegated-owner-test')->plainTextToken;
        $list = $this->withToken($delegatedToken)->getJson('/api/v1/users')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($list, '/users', 'get');
        $listedIds = array_column($list->json('data'), 'id');
        $this->assertContains((string) $ownerA->id, $listedIds);
        $this->assertContains((string) $delegatedOwnerId, $listedIds);
        $this->assertNotContains((string) $ownerB->id, $listedIds);
    }

    public function test_owner_gets_404_for_another_warungs_user_and_leaves_it_unchanged(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $ownerA = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $managerB = User::factory()->create([
            'warung_id' => $warungB->id,
            'role' => 'manager',
            'nama' => 'Manager Tetap Warung B',
        ]);
        $token = $ownerA->createToken('user-feature-test')->plainTextToken;

        $detail = $this->withToken($token)
            ->getJson('/api/v1/users/'.$managerB->id)
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($detail, '/users/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'NOT_FOUND');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$managerB->id, ['nama' => 'Pemilik Menyerang'])
            ->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($update, '/users/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'NOT_FOUND');

        $this->assertDatabaseHas('users', [
            'id' => $managerB->id,
            'warung_id' => $warungB->id,
            'nama' => 'Manager Tetap Warung B',
            'role' => 'manager',
        ]);
    }

    public function test_owner_cannot_inject_warung_id_or_elevated_role_on_create_or_update(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $target = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'manager']);
        $token = $owner->createToken('user-feature-test')->plainTextToken;
        $createPayload = [
            'nama' => 'Injected User',
            'username' => 'injected-user',
            'password' => 'injected-test-password',
            'role' => 'manager',
        ];

        $createWithTenant = $this->withToken($token)->postJson('/api/v1/users', [
            ...$createPayload,
            'warung_id' => $warungB->id,
        ])->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($createWithTenant, '/users', 'post');
        $this->assertD13ErrorEnvelope($createWithTenant, 'VALIDATION_ERROR', 'warung_id');

        $createWithElevatedRole = $this->withToken($token)->postJson('/api/v1/users', [
            ...$createPayload,
            'username' => 'injected-superadmin',
            'role' => 'superadmin',
        ])->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($createWithElevatedRole, '/users', 'post');
        $this->assertD13ErrorEnvelope($createWithElevatedRole, 'VALIDATION_ERROR', 'role');

        $updateWithTenant = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$target->id, ['warung_id' => $warungB->id])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($updateWithTenant, '/users/{id}', 'patch');
        $this->assertD13ErrorEnvelope($updateWithTenant, 'VALIDATION_ERROR', 'warung_id');

        $updateWithElevatedRole = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$target->id, ['role' => 'superadmin'])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($updateWithElevatedRole, '/users/{id}', 'patch');
        $this->assertD13ErrorEnvelope($updateWithElevatedRole, 'VALIDATION_ERROR', 'role');

        $this->assertSame(2, User::query()->count());
        $this->assertDatabaseMissing('users', ['username' => 'injected-user']);
        $this->assertDatabaseMissing('users', ['username' => 'injected-superadmin']);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'warung_id' => $warungA->id,
            'role' => 'manager',
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonOwnerRoles(): array
    {
        return [
            'manager' => ['manager'],
            'cashier' => ['kasir'],
            'superadmin' => ['superadmin'],
        ];
    }

    #[DataProvider('nonOwnerRoles')]
    public function test_non_owner_roles_cannot_use_owner_user_administration(string $role): void
    {
        $warung = Warung::factory()->create();
        $actor = User::factory()->create([
            'warung_id' => $role === 'superadmin' ? null : $warung->id,
            'role' => $role,
        ]);
        $target = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'manager',
            'nama' => 'Manager Tetap',
        ]);
        $token = $actor->createToken('user-feature-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($list, '/users', 'get');
        $this->assertD13ErrorEnvelope($list, 'FORBIDDEN');

        $create = $this->withToken($token)->postJson('/api/v1/users', [
            'nama' => 'Tidak Diizinkan',
            'username' => 'forbidden-user-'.$role,
            'password' => 'forbidden-test-password',
            'role' => 'manager',
        ])->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($create, '/users', 'post');
        $this->assertD13ErrorEnvelope($create, 'FORBIDDEN');

        $detail = $this->withToken($token)
            ->getJson('/api/v1/users/'.$target->id)
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($detail, '/users/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'FORBIDDEN');

        $updatePayload = ['nama' => 'Nama Tidak Diizinkan'];
        $this->assertOperationRequestMatchesOpenApi($updatePayload, [], '/users/{id}', 'patch');
        $update = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$target->id, $updatePayload)
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($update, '/users/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');

        $this->assertSame(2, User::query()->count());
        $this->assertDatabaseMissing('users', ['username' => 'forbidden-user-'.$role]);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'warung_id' => $warung->id,
            'nama' => 'Manager Tetap',
            'role' => 'manager',
        ]);
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode, ?string $field = null): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame($expectedCode, $body['code']);
        $this->assertIsString($body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );

        if ($field !== null) {
            $this->assertArrayHasKey($field, $body['errors']);
        }
    }
}
