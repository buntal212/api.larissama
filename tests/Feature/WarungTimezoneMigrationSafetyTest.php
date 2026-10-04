<?php

namespace Tests\Feature;

use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WarungTimezoneMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

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
