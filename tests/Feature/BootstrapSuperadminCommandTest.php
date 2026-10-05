<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_one_active_superadmin_on_an_empty_database(): void
    {
        $this->artisan('app:bootstrap-superadmin')
            ->expectsQuestion('Nama superadmin', 'Admin Platform')
            ->expectsQuestion('Username superadmin', 'adminplatform')
            ->expectsQuestion('Email superadmin (boleh kosong)', 'admin@example.test')
            ->expectsQuestion('Password superadmin (minimum 8 karakter)', 'rahasia-aman-123')
            ->expectsQuestion('Ulangi password superadmin', 'rahasia-aman-123')
            ->expectsOutputToContain('Superadmin pertama berhasil dibuat.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('users', 1);
        $admin = User::query()->sole();
        $this->assertSame('Admin Platform', $admin->nama);
        $this->assertSame('adminplatform', $admin->username);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame('superadmin', $admin->role);
        $this->assertNull($admin->warung_id);
        $this->assertTrue($admin->aktif);
        $this->assertTrue(Hash::check('rahasia-aman-123', $admin->password));
    }

    public function test_bootstrap_refuses_to_run_when_any_user_already_exists(): void
    {
        User::factory()->create();

        $this->artisan('app:bootstrap-superadmin')
            ->expectsOutputToContain('Bootstrap ditolak: tabel users harus kosong.')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_username_or_short_password_does_not_create_a_user(): void
    {
        $this->artisan('app:bootstrap-superadmin')
            ->expectsQuestion('Nama superadmin', 'Admin Platform')
            ->expectsQuestion('Username superadmin', 'AdminPlatform')
            ->expectsQuestion('Email superadmin (boleh kosong)', '')
            ->expectsQuestion('Password superadmin (minimum 8 karakter)', 'short')
            ->expectsQuestion('Ulangi password superadmin', 'short')
            ->expectsOutputToContain('The username field must be lowercase.')
            ->expectsOutputToContain('The password field must be at least 8 characters.')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unconfirmed_password_does_not_create_a_user(): void
    {
        $this->artisan('app:bootstrap-superadmin')
            ->expectsQuestion('Nama superadmin', 'Admin Platform')
            ->expectsQuestion('Username superadmin', 'adminplatform')
            ->expectsQuestion('Email superadmin (boleh kosong)', '')
            ->expectsQuestion('Password superadmin (minimum 8 karakter)', 'rahasia-aman-123')
            ->expectsQuestion('Ulangi password superadmin', 'password-berbeda-123')
            ->expectsOutputToContain('The password field confirmation does not match.')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }
}
