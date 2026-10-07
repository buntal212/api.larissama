<?php

namespace Tests\Feature;

use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WarungRegistrationApprovalMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_refuses_to_drop_pending_registration_state(): void
    {
        $warung = Warung::factory()->create([
            'aktif' => false,
            'pendaftaran_disetujui' => false,
        ]);
        $migration = require database_path('migrations/2026_10_07_124412_add_registration_approval_to_warungs_table.php');

        try {
            $migration->down();
            $this->fail('Rollback must preserve pending registration state.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Rollback persetujuan pendaftaran ditolak karena ada warung yang masih menunggu persetujuan.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue(Schema::hasColumn('warungs', 'pendaftaran_disetujui'));
        $this->assertFalse($warung->fresh()->pendaftaran_disetujui);
    }
}
