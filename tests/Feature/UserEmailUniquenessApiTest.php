<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserEmailUniquenessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_create_user_with_email_used_in_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        User::factory()->create([
            'warung_id' => $warungB->id,
            'email' => 'shared@example.com',
        ]);
        $token = $owner->createToken('user-email-uniqueness-test')->plainTextToken;
        $payload = [
            'nama' => 'Email Bentrok',
            'username' => 'email-bentrok-create',
            'email' => 'shared@example.com',
            'password' => 'email-test-password',
            'role' => 'kasir',
        ];

        $this->assertOperationRequestMatchesOpenApi($payload, [], '/users', 'post');
        $response = $this->withToken($token)
            ->postJson('/api/v1/users', $payload)
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/users', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR', 'email');
        $this->assertDatabaseMissing('users', ['username' => 'email-bentrok-create']);
    }

    public function test_owner_cannot_update_user_to_email_used_in_another_warung(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warungA->id, 'role' => 'owner']);
        $target = User::factory()->create([
            'warung_id' => $warungA->id,
            'email' => 'original@example.com',
            'role' => 'manager',
        ]);
        User::factory()->create([
            'warung_id' => $warungB->id,
            'email' => 'shared@example.com',
        ]);
        $token = $owner->createToken('user-email-uniqueness-test')->plainTextToken;
        $payload = ['email' => 'shared@example.com'];

        $this->assertOperationRequestMatchesOpenApi($payload, [], '/users/{id}', 'patch');
        $response = $this->withToken($token)
            ->patchJson('/api/v1/users/'.$target->id, $payload)
            ->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/users/{id}', 'patch');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR', 'email');
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'original@example.com',
        ]);
    }

    public function test_email_can_be_null_or_omitted_when_owner_creates_users(): void
    {
        $warung = Warung::factory()->create();
        $owner = User::factory()->create(['warung_id' => $warung->id, 'role' => 'owner']);
        $token = $owner->createToken('user-email-uniqueness-test')->plainTextToken;
        $payloads = [
            [
                'nama' => 'Email Null',
                'username' => 'email-null-explicit',
                'email' => null,
                'password' => 'email-test-password',
                'role' => 'manager',
            ],
            [
                'nama' => 'Email Tidak Dikirim',
                'username' => 'email-null-omitted',
                'password' => 'email-test-password',
                'role' => 'kasir',
            ],
        ];

        foreach ($payloads as $payload) {
            $this->assertOperationRequestMatchesOpenApi($payload, [], '/users', 'post');
            $response = $this->withToken($token)
                ->postJson('/api/v1/users', $payload)
                ->assertCreated();

            $this->assertOperationResponseMatchesOpenApi($response, '/users', 'post');
            $this->assertNull($response->json('data.email'));
            $this->assertDatabaseHas('users', [
                'id' => (int) $response->json('data.id'),
                'warung_id' => $warung->id,
                'email' => null,
            ]);
        }
    }

    public function test_database_rejects_duplicate_email_globally_and_allows_multiple_nulls(): void
    {
        $warungA = Warung::factory()->create();
        $warungB = Warung::factory()->create();
        User::factory()->create([
            'warung_id' => $warungA->id,
            'email' => 'database-unique@example.com',
        ]);

        try {
            User::factory()->create([
                'warung_id' => $warungB->id,
                'email' => 'database-unique@example.com',
            ]);
            $this->fail('The global email unique index should reject a duplicate in another warung.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('users_email_unique', $exception->getMessage());
        }

        User::factory()->create(['warung_id' => $warungA->id, 'email' => null]);
        User::factory()->create(['warung_id' => $warungB->id, 'email' => null]);

        $this->assertSame(3, User::query()->count());
        $this->assertSame(2, User::query()->whereNull('email')->count());
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode, string $field): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame($expectedCode, $body['code']);
        $this->assertIsString($body['message']);
        $this->assertArrayHasKey($field, $body['errors']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );
    }
}
