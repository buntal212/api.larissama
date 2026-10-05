<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BootstrapSuperadmin extends Command
{
    protected $signature = 'app:bootstrap-superadmin';

    protected $description = 'Create the first platform superadmin on an empty users table';

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            $this->components->error('Bootstrap superadmin hanya didukung pada MySQL.');

            return self::FAILURE;
        }

        $database = (string) $connection->getDatabaseName();
        $lockName = 'larissama:bootstrap:'.substr(hash('sha256', $database), 0, 32);
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            $this->components->error('Bootstrap superadmin sedang berjalan atau gagal memperoleh lock database.');

            return self::FAILURE;
        }

        try {
            if (User::query()->exists()) {
                $this->components->error('Bootstrap ditolak: tabel users harus kosong.');

                return self::FAILURE;
            }

            $input = [
                'nama' => trim((string) $this->ask('Nama superadmin')),
                'username' => trim((string) $this->ask('Username superadmin')),
                'email' => trim((string) $this->ask('Email superadmin (boleh kosong)')) ?: null,
            ];
            $input['password'] = (string) $this->secret('Password superadmin (minimum 8 karakter)');
            $confirmation = (string) $this->secret('Ulangi password superadmin');

            $validator = Validator::make([
                ...$input,
                'password_confirmation' => $confirmation,
            ], [
                'nama' => ['required', 'string', 'min:1', 'max:150'],
                'username' => ['required', 'string', 'min:1', 'max:100', 'lowercase', Rule::unique('users', 'username')],
                'email' => ['nullable', 'email', 'max:150', 'lowercase', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->components->error($error);
                }

                return self::FAILURE;
            }

            $user = DB::transaction(fn (): User => User::query()->create([
                'nama' => $input['nama'],
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => 'superadmin',
                'warung_id' => null,
                'aktif' => true,
            ]));

            $this->components->info('Superadmin pertama berhasil dibuat.');
            $this->line('Username: '.$user->username);

            return self::SUCCESS;
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}
