<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_me_return_the_current_identity_without_secrets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04T03:00:00Z'));

        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $password = 'auth-feature-secret';
        $user = User::factory()->create([
            'warung_id' => $warung->id,
            'username' => 'auth-owner',
            'email' => null,
            'password' => $password,
            'role' => 'owner',
        ]);
        $logHandler = new TestHandler;
        Log::getLogger()->pushHandler($logHandler);

        $loginRequest = [
            'username' => $user->username,
            'password' => $password,
        ];
        $this->assertOperationRequestMatchesOpenApi($loginRequest, [], '/auth/login', 'post');
        $login = $this->postJson('/api/v1/auth/login', $loginRequest)->assertOk();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');

        $loginBody = $login->json();
        $this->assertEqualsCanonicalizing(['data'], array_keys($loginBody));
        $this->assertEqualsCanonicalizing(
            ['access_token', 'token_type', 'expires_at', 'user', 'warung'],
            array_keys($loginBody['data']),
        );
        $this->assertIsString($loginBody['data']['access_token']);
        $this->assertSame('Bearer', $loginBody['data']['token_type']);
        $this->assertSame('2026-11-03T03:00:00.000000Z', $loginBody['data']['expires_at']);
        $this->assertUserResource($loginBody['data']['user'], $user, $warung);
        $this->assertWarungResource($loginBody['data']['warung'], $warung);
        $this->assertStringNotContainsString($password, $login->getContent());
        $this->assertTrue(Hash::check($password, $user->refresh()->password));
        $this->assertNotSame($password, $user->password);
        $this->assertSame(1, $user->tokens()->count());
        $logOutput = implode(PHP_EOL, array_map(
            static fn ($record): string => $record->message.' '.json_encode($record->context),
            $logHandler->getRecords(),
        ));
        $this->assertStringNotContainsString($password, $logOutput);
        $this->assertStringNotContainsString($loginBody['data']['access_token'], $logOutput);

        $me = $this->withToken($loginBody['data']['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($me, '/auth/me', 'get');

        $meBody = $me->json();
        $this->assertEqualsCanonicalizing(['data'], array_keys($meBody));
        $this->assertEqualsCanonicalizing(['user', 'warung'], array_keys($meBody['data']));
        $this->assertUserResource($meBody['data']['user'], $user, $warung);
        $this->assertWarungResource($meBody['data']['warung'], $warung);
        $this->assertArrayNotHasKey('access_token', $meBody['data']);
        $this->assertStringNotContainsString($password, $me->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedRoleCases(): array
    {
        return [
            'superadmin has no tenant context' => ['superadmin'],
            'owner receives tenant context' => ['owner'],
            'manager receives tenant context' => ['manager'],
            'cashier receives tenant context' => ['kasir'],
        ];
    }

    #[DataProvider('supportedRoleCases')]
    public function test_supported_roles_login_with_their_expected_warung_context(string $role): void
    {
        $warung = $role === 'superadmin' ? null : Warung::factory()->create();
        $user = User::factory()->create([
            'warung_id' => $warung?->id,
            'role' => $role,
        ]);

        $loginRequest = [
            'username' => $user->username,
            'password' => 'password',
        ];
        $this->assertOperationRequestMatchesOpenApi($loginRequest, [], '/auth/login', 'post');
        $login = $this->postJson('/api/v1/auth/login', $loginRequest)->assertOk();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');

        $this->assertSame($role, $login->json('data.user.role'));
        $this->assertSame(
            $warung === null ? null : (string) $warung->id,
            $login->json('data.user.warung_id'),
        );
        $this->assertSame($warung === null, $login->json('data.warung') === null);

        $me = $this->withToken($login->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($me, '/auth/me', 'get');

        $this->assertSame($user->username, $me->json('data.user.username'));
        $this->assertSame($role, $me->json('data.user.role'));
        $this->assertSame(
            $warung === null ? null : (string) $warung->id,
            $me->json('data.user.warung_id'),
        );
        $this->assertSame($warung === null, $me->json('data.warung') === null);
    }

    public function test_invalid_credentials_return_the_same_generic_401_error(): void
    {
        $user = User::factory()->create(['username' => 'known-auth-user']);

        $wrongPasswordRequest = [
            'username' => $user->username,
            'password' => 'wrong-auth-secret',
        ];
        $unknownUsernameRequest = [
            'username' => 'unknown-auth-user',
            'password' => 'wrong-auth-secret',
        ];
        $this->assertOperationRequestMatchesOpenApi($wrongPasswordRequest, [], '/auth/login', 'post');
        $this->assertOperationRequestMatchesOpenApi($unknownUsernameRequest, [], '/auth/login', 'post');

        $wrongPassword = $this->postJson('/api/v1/auth/login', $wrongPasswordRequest)->assertUnauthorized();
        $unknownUsername = $this->postJson('/api/v1/auth/login', $unknownUsernameRequest)->assertUnauthorized();

        $this->assertOperationResponseMatchesOpenApi($wrongPassword, '/auth/login', 'post');
        $this->assertOperationResponseMatchesOpenApi($unknownUsername, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($wrongPassword, 'UNAUTHENTICATED');
        $this->assertD13ErrorEnvelope($unknownUsername, 'UNAUTHENTICATED');
        $this->assertSame('Username atau password tidak valid.', $wrongPassword->json('message'));
        $this->assertSame($wrongPassword->json('message'), $unknownUsername->json('message'));
        $this->assertStringNotContainsString('wrong-auth-secret', $wrongPassword->getContent());
        $this->assertStringNotContainsString('unknown-auth-user', $unknownUsername->getContent());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_empty_login_fields_return_a_d13_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('username', $response->json('errors'));
        $this->assertArrayHasKey('password', $response->json('errors'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_login_rejects_unknown_fields_and_does_not_issue_a_token(): void
    {
        $user = User::factory()->create([
            'username' => 'unknown-login-field-user',
            'password' => 'valid-login-secret',
        ]);
        $payload = [
            'username' => $user->username,
            'password' => 'valid-login-secret',
            'warung_id' => '999999999',
        ];

        $this->assertOperationRequestDoesNotMatchOpenApi($payload, '/auth/login', 'post');
        $response = $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();

        $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($response, 'VALIDATION_ERROR', 'warung_id');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function inactiveAccountCases(): array
    {
        return [
            'inactive user' => ['user'],
            'inactive warung' => ['warung'],
        ];
    }

    #[DataProvider('inactiveAccountCases')]
    public function test_inactive_account_cannot_login_or_use_an_existing_token(string $inactiveTarget): void
    {
        $warung = Warung::factory()->create();
        $user = User::factory()->create(['warung_id' => $warung->id, 'role' => 'manager']);
        $token = $user->createToken('before-deactivation')->plainTextToken;

        if ($inactiveTarget === 'user') {
            $user->update(['aktif' => false]);
        } else {
            $warung->update(['aktif' => false]);
        }

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertForbidden();
        $me = $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $protectedResource = $this->withToken($token)->getJson('/api/v1/warung')->assertForbidden();

        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
        $this->assertOperationResponseMatchesOpenApi($me, '/auth/me', 'get');
        $this->assertD13ErrorEnvelope($login, 'FORBIDDEN');
        $this->assertD13ErrorEnvelope($me, 'FORBIDDEN');
        $this->assertD13ErrorEnvelope($protectedResource, 'FORBIDDEN');
        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function unavailableTenantTimezoneCases(): array
    {
        $cases = [];

        foreach (['owner', 'manager', 'kasir'] as $role) {
            $cases["{$role} with NULL timezone"] = [$role, null];
            $cases["{$role} with invalid timezone"] = [$role, 'Invalid/Timezone'];
        }

        return $cases;
    }

    #[DataProvider('unavailableTenantTimezoneCases')]
    public function test_missing_or_invalid_warung_timezone_blocks_login_and_existing_tokens(string $role, ?string $timezone): void
    {
        $warung = Warung::factory()->create(['timezone' => $timezone]);
        $user = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $token = $user->createToken('timezone-unavailable')->plainTextToken;

        $loginRequest = [
            'username' => $user->username,
            'password' => 'password',
        ];
        $this->assertOperationRequestMatchesOpenApi($loginRequest, [], '/auth/login', 'post');

        $login = $this->postJson('/api/v1/auth/login', $loginRequest)->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($login, 'FORBIDDEN');
        $this->assertSame(1, $user->tokens()->count(), 'A denied login must not issue another token.');

        Auth::forgetGuards();
        $me = $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($me, '/auth/me', 'get');
        $this->assertD13ErrorEnvelope($me, 'FORBIDDEN');

        Auth::forgetGuards();
        $protectedResource = $this->withToken($token)->getJson('/api/v1/warung')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($protectedResource, '/warung', 'get');
        $this->assertD13ErrorEnvelope($protectedResource, 'FORBIDDEN');
        $this->assertSame(1, $user->tokens()->count(), 'The existing token remains stored but cannot access tenant routes.');
    }

    /**
     * @return array<string, array{string, string|null, string|null, bool}>
     */
    public static function activeDateCases(): array
    {
        return [
            'Jakarta start and end are inclusive' => ['Asia/Jakarta', '2026-10-04', '2026-10-04', true],
            'Jakarta start date is in the future' => ['Asia/Jakarta', '2026-10-05', null, false],
            'Jakarta end date has passed' => ['Asia/Jakarta', null, '2026-10-03', false],
            'New York uses the previous local date' => ['America/New_York', '2026-10-04', null, false],
            'New York local start and end are inclusive' => ['America/New_York', '2026-10-03', '2026-10-03', true],
            'Null start leaves the end bound active' => ['Asia/Jakarta', null, '2026-10-04', true],
            'Null end leaves the start bound active' => ['Asia/Jakarta', '2026-10-04', null, true],
            'Both dates null have no date bound' => ['Asia/Jakarta', null, null, true],
        ];
    }

    #[DataProvider('activeDateCases')]
    public function test_login_uses_local_inclusive_active_dates_and_null_bounds(
        string $timezone,
        ?string $startDate,
        ?string $endDate,
        bool $expectedAllowed,
    ): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-04T00:30:00Z'));

        $warung = Warung::factory()->create([
            'timezone' => $timezone,
            'tanggal_mulai' => $startDate,
            'tanggal_berakhir' => $endDate,
        ]);
        $user = User::factory()->create(['warung_id' => $warung->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        if ($expectedAllowed) {
            $response->assertOk();
            $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');
            $this->assertSame(1, $user->tokens()->count());
        } else {
            $response->assertForbidden();
            $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');
            $this->assertD13ErrorEnvelope($response, 'FORBIDDEN');
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_token_is_valid_before_thirty_days_and_rejected_at_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04T03:00:00Z'));

        $user = User::factory()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk();
        $this->assertOperationResponseMatchesOpenApi($login, '/auth/login', 'post');
        $token = $login->json('data.access_token');

        $this->travelTo(CarbonImmutable::parse('2026-11-03T02:59:59Z'));
        $active = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($active, '/auth/me', 'get');

        $this->travelTo(CarbonImmutable::parse('2026-11-03T03:00:00Z'));
        Auth::forgetGuards();
        $expired = $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->assertOperationResponseMatchesOpenApi($expired, '/auth/me', 'get');
        $this->assertD13ErrorEnvelope($expired, 'UNAUTHENTICATED');
    }

    public function test_logout_returns_empty_204_and_revokes_only_the_presented_token(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('first-session');
        $secondToken = $user->createToken('second-session');

        $logout = $this->withToken($firstToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertOperationResponseMatchesOpenApi($logout, '/auth/logout', 'post');
        $this->assertSame('', $logout->getContent());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $firstToken->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $secondToken->accessToken->getKey()]);
        $this->assertSame(1, $user->tokens()->count());

        Auth::forgetGuards();
        $revoked = $this->withToken($firstToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
        $this->assertOperationResponseMatchesOpenApi($revoked, '/auth/me', 'get');
        $this->assertD13ErrorEnvelope($revoked, 'UNAUTHENTICATED');

        Auth::forgetGuards();
        $secondSession = $this->withToken($secondToken->plainTextToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertOperationResponseMatchesOpenApi($secondSession, '/auth/me', 'get');
    }

    public function test_login_allows_five_attempts_then_returns_a_d13_rate_limit_error(): void
    {
        $username = strtolower('RateLimit-'.fake()->uuid());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'username' => $username,
                'password' => 'invalid-rate-limit-secret',
            ])->assertUnauthorized();

            $this->assertOperationResponseMatchesOpenApi($response, '/auth/login', 'post');
            $this->assertD13ErrorEnvelope($response, 'UNAUTHENTICATED');
        }

        $limited = $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => 'invalid-rate-limit-secret',
        ])->assertTooManyRequests();

        $this->assertOperationResponseMatchesOpenApi($limited, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($limited, 'RATE_LIMITED');
        $this->assertStringNotContainsString('invalid-rate-limit-secret', $limited->getContent());

        $otherIp = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->postJson('/api/v1/auth/login', [
                'username' => $username,
                'password' => 'invalid-rate-limit-secret',
            ])
            ->assertUnauthorized();

        $this->assertOperationResponseMatchesOpenApi($otherIp, '/auth/login', 'post');
        $this->assertD13ErrorEnvelope($otherIp, 'UNAUTHENTICATED');
    }

    /**
     * @param  array<string, mixed>  $userResource
     */
    private function assertUserResource(array $userResource, User $user, Warung $warung): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'warung_id', 'nama', 'username', 'email', 'role', 'aktif', 'created_at', 'updated_at'],
            array_keys($userResource),
        );
        $this->assertSame((string) $user->id, $userResource['id']);
        $this->assertSame((string) $warung->id, $userResource['warung_id']);
        $this->assertSame($user->username, $userResource['username']);
        $this->assertArrayNotHasKey('password', $userResource);
        $this->assertArrayNotHasKey('remember_token', $userResource);
    }

    /**
     * @param  array<string, mixed>  $warungResource
     */
    private function assertWarungResource(array $warungResource, Warung $warung): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'created_at', 'updated_at'],
            array_keys($warungResource),
        );
        $this->assertSame((string) $warung->id, $warungResource['id']);
        $this->assertSame($warung->timezone, $warungResource['timezone']);
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
