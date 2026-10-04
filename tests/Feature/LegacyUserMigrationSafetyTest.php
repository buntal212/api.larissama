<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LegacyUserMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_adaptation_migration_aborts_without_mutating_non_empty_users_table(): void
    {
        $warung = Warung::factory()->create();
        $user = User::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Legacy User',
            'username' => 'legacy-user',
            'email' => 'legacy@example.com',
        ]);
        $columnsBefore = Schema::getColumnListing('users');
        $rowBefore = (array) DB::table('users')->where('id', $user->id)->first();
        $migration = require database_path('migrations/2026_10_04_065854_adapt_users_for_larissama_tenants.php');
        $exception = null;

        try {
            $migration->up();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Migration users dihentikan: isi username, role, dan warung_id untuk setiap user lama sebelum migrasi.',
            $exception->getMessage(),
        );
        $this->assertSame($columnsBefore, Schema::getColumnListing('users'));
        $this->assertSame($rowBefore, (array) DB::table('users')->where('id', $user->id)->first());
    }

    public function test_users_rollback_preserves_user_records_and_schema(): void
    {
        $warung = Warung::factory()->create();
        $user = User::factory()->create([
            'warung_id' => $warung->id,
            'username' => 'rollback-user',
        ]);
        $columnsBefore = Schema::getColumnListing('users');
        $rowBefore = (array) DB::table('users')->where('id', $user->id)->first();
        $migration = require database_path('migrations/2026_10_04_065854_adapt_users_for_larissama_tenants.php');
        $exception = null;

        try {
            $migration->down();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Rollback users ditolak karena ada data user atau token; gunakan migration maju untuk perubahan schema.',
            $exception->getMessage(),
        );
        $this->assertSame($columnsBefore, Schema::getColumnListing('users'));
        $this->assertSame($rowBefore, (array) DB::table('users')->where('id', $user->id)->first());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_users_rollback_preserves_tokens_when_no_users_remain(): void
    {
        $user = User::factory()->create();
        $user->createToken('rollback-guard-test');
        $tokenBefore = (array) DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->first();
        $tokenId = $tokenBefore['id'];
        DB::table('users')->where('id', $user->id)->delete();
        $columnsBefore = Schema::getColumnListing('users');
        $migration = require database_path('migrations/2026_10_04_065854_adapt_users_for_larissama_tenants.php');
        $exception = null;

        try {
            $migration->down();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Rollback users ditolak karena ada data user atau token; gunakan migration maju untuk perubahan schema.',
            $exception->getMessage(),
        );
        $this->assertSame($columnsBefore, Schema::getColumnListing('users'));
        $this->assertDatabaseCount('users', 0);
        $this->assertSame($tokenBefore, (array) DB::table('personal_access_tokens')->where('id', $tokenId)->first());
    }

    public function test_timezone_rollback_preserves_warung_rows_and_schema(): void
    {
        $warung = Warung::factory()->create(['timezone' => 'Asia/Jakarta']);
        $columnsBefore = Schema::getColumnListing('warungs');
        $rowBefore = (array) DB::table('warungs')->where('id', $warung->id)->first();
        $migration = require database_path('migrations/2026_10_04_073156_add_timezone_to_warungs_table.php');
        $exception = null;

        try {
            $migration->down();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Rollback timezone ditolak karena ada pengaturan zona waktu warung; gunakan migration maju.',
            $exception->getMessage(),
        );
        $this->assertSame($columnsBefore, Schema::getColumnListing('warungs'));
        $this->assertSame($rowBefore, (array) DB::table('warungs')->where('id', $warung->id)->first());
    }
}
