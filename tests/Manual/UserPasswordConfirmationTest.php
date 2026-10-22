<?php

namespace Tests\Manual;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_password_can_be_changed_only_with_matching_confirmation(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('manual-user-password-confirmation')->plainTextToken;

        $profileUpdate = ['nama' => 'Owner Diperbarui'];
        $this->assertOperationRequestMatchesOpenApi($profileUpdate, [], '/users/{id}', 'patch');
        $selfUpdate = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$owner->id, $profileUpdate)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($selfUpdate, '/users/{id}', 'patch');
        $this->assertSame('Owner Diperbarui', $selfUpdate->json('data.nama'));

        $idMutation = ['id' => (string) ($owner->id + 1), 'nama' => 'Nama Tidak Boleh Tersimpan'];
        $this->assertOperationRequestDoesNotMatchOpenApi($idMutation, '/users/{id}', 'patch');
        $idMutationResponse = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$owner->id, $idMutation)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($idMutationResponse, '/users/{id}', 'patch');
        $this->assertArrayHasKey('id', $idMutationResponse->json('errors'));
        $this->assertSame($owner->id, $owner->refresh()->id);
        $this->assertSame('Owner Diperbarui', $owner->nama);

        $matchingPayload = [
            'password' => 'new-valid-password',
            'password_confirmation' => 'new-valid-password',
        ];
        $this->assertOperationRequestMatchesOpenApi($matchingPayload, [], '/users/{id}', 'patch');

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$owner->id, $matchingPayload)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/users/{id}', 'patch');
        $this->assertTrue(Hash::check('new-valid-password', $owner->refresh()->password));
        $this->assertStringNotContainsString('new-valid-password', $updated->getContent());

        $mismatchingPayload = [
            'password' => 'another-valid-password',
            'password_confirmation' => 'different-valid-password',
        ];
        $this->assertOperationRequestMatchesOpenApi($mismatchingPayload, [], '/users/{id}', 'patch');

        $rejected = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$owner->id, $mismatchingPayload)
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($rejected, '/users/{id}', 'patch');
        $this->assertArrayHasKey('password', $rejected->json('errors'));
        $this->assertTrue(Hash::check('new-valid-password', $owner->refresh()->password));
    }
}
