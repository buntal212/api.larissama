<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordMinimumConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioned_owner_password_requires_eight_characters(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $superadminToken = $superadmin->createToken('password-minimum-admin')->plainTextToken;
        $provisionPayload = [
            'nama' => 'Warung Password',
            'timezone' => 'Asia/Jakarta',
            'alamat' => null,
            'telepon' => null,
            'owner' => [
                'nama' => 'Owner Password',
                'username' => 'owner-password',
                'email' => null,
                'password' => '1234567',
            ],
        ];
        $this->assertOperationRequestDoesNotMatchOpenApi($provisionPayload, '/admin/warungs', 'post');
        $provision = $this->withToken($superadminToken)
            ->postJson('/api/v1/admin/warungs', $provisionPayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($provision, '/admin/warungs', 'post');
        $this->assertArrayHasKey('owner.password', $provision->json('errors'));
        $this->assertSame(0, Warung::query()->count());
        $this->assertSame(1, User::query()->count());

        $minimumProvisionPayload = $provisionPayload;
        $minimumProvisionPayload['owner']['username'] = 'owner-password-eight';
        $minimumProvisionPayload['owner']['password'] = '12345678';
        $this->assertOperationRequestMatchesOpenApi($minimumProvisionPayload, [], '/admin/warungs', 'post');
        $minimumProvision = $this->withToken($superadminToken)
            ->postJson('/api/v1/admin/warungs', $minimumProvisionPayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($minimumProvision, '/admin/warungs', 'post');
        $this->assertDatabaseHas('users', ['username' => 'owner-password-eight']);
    }

    public function test_tenant_user_creation_and_password_replacement_require_eight_characters(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $ownerToken = $owner->createToken('password-minimum-owner')->plainTextToken;
        $createPayload = [
            'nama' => 'Kasir Baru',
            'username' => 'kasir-password-min',
            'password' => '1234567',
            'role' => 'kasir',
        ];
        $this->assertOperationRequestDoesNotMatchOpenApi($createPayload, '/users', 'post');
        $create = $this->withToken($ownerToken)
            ->postJson('/api/v1/users', $createPayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($create, '/users', 'post');
        $this->assertArrayHasKey('password', $create->json('errors'));
        $this->assertSame(1, User::query()->count());

        $minimumCreatePayload = $createPayload;
        $minimumCreatePayload['username'] = 'kasir-password-eight';
        $minimumCreatePayload['password'] = '12345678';
        $this->assertOperationRequestMatchesOpenApi($minimumCreatePayload, [], '/users', 'post');
        $minimumCreate = $this->withToken($ownerToken)
            ->postJson('/api/v1/users', $minimumCreatePayload)
            ->assertCreated();
        $this->assertOperationResponseMatchesOpenApi($minimumCreate, '/users', 'post');
        $this->assertDatabaseHas('users', ['username' => 'kasir-password-eight']);

        $target = User::factory()->create([
            'warung_id' => $warung->id,
            'role' => 'kasir',
            'password' => 'previous-valid-password',
        ]);
        $updatePayload = ['password' => '1234567'];
        $this->assertOperationRequestDoesNotMatchOpenApi($updatePayload, '/users/{id}', 'patch');
        $update = $this->withToken($ownerToken)
            ->patchJson('/api/v1/users/'.$target->id, $updatePayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($update, '/users/{id}', 'patch');
        $this->assertArrayHasKey('password', $update->json('errors'));
        $this->assertTrue(Hash::check('previous-valid-password', $target->refresh()->password));
    }
}
