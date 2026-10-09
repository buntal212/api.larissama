<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProvisionWarungAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function beginDatabaseTransaction(): void
    {
        // A MySQL trigger is installed and removed inside this disposable test database.
    }

    public function test_warung_is_rolled_back_when_owner_insert_fails(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-atomicity-test')->plainTextToken;
        $ownerUsername = 'fail-owner-'.Str::lower(Str::random(16));
        $triggerName = 'test_fail_admin_owner_'.Str::lower(Str::random(8));
        $triggerInstalled = false;

        try {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$triggerName} BEFORE INSERT ON users FOR EACH ROW
                BEGIN
                    IF NEW.username = '{$ownerUsername}' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test owner insert failure';
                    END IF;
                END
                SQL);
            $triggerInstalled = true;

            $response = $this->withToken($token)
                ->postJson('/api/v1/admin/warungs', [
                    'nama' => 'Warung Rollback Uji',
                    'timezone' => 'Asia/Jakarta',
                    'alamat' => null,
                    'telepon' => null,
                    'owner' => [
                        'nama' => 'Owner Rollback Uji',
                        'username' => $ownerUsername,
                        'email' => null,
                        'password' => 'owner-test-password',
                    ],
                ])
                ->assertInternalServerError()
                ->assertDontSee('test owner insert failure');

            $this->assertOperationResponseMatchesOpenApi($response, '/admin/warungs', 'post');
            $this->assertD13ErrorEnvelope($response, 'INTERNAL_ERROR');
            $this->assertDatabaseMissing('users', ['username' => $ownerUsername]);
            $this->assertSame(1, DB::table('users')->count());
            $this->assertSame(0, DB::table('warungs')->count());
        } finally {
            if ($triggerInstalled) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
            }

            DB::table('users')->where('username', $ownerUsername)->delete();
            DB::table('warungs')->where('nama', 'Warung Rollback Uji')->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $superadmin->getKey())
                ->delete();
            DB::table('users')->where('id', $superadmin->getKey())->delete();
        }
    }

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode): void
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
    }
}
