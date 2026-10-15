<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class PerformanceHistorySeeder extends Seeder
{
    private const int SALES_PER_WARUNG = 5000;

    public function run(): void
    {
        $connection = DB::connection();

        if (app()->environment() !== 'testing'
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'larissama_performance') {
            throw new LogicException('PerformanceHistorySeeder hanya boleh berjalan di MySQL disposable larissama_performance dengan APP_ENV=testing.');
        }

        if (DB::table('penjualans')->where('no_transaksi', 'like', 'PERF-HISTORY-%')->exists()
            || DB::table('pembelians')->where('no_transaksi', 'like', 'PERF-HISTORY-%')->exists()) {
            throw new LogicException('Dataset performance sudah ada. Hapus container database disposable lalu mulai ulang Compose.');
        }

        $warung = Warung::factory()->create(['nama' => 'Performance History Fixture']);
        $manager = User::factory()->create([
            'warung_id' => $warung->id,
            'nama' => 'Performance History Manager',
            'role' => 'manager',
        ]);
        $anchor = CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC');
        $sales = [];
        $purchases = [];

        for ($rowNumber = 0; $rowNumber < self::SALES_PER_WARUNG; $rowNumber++) {
            $timestamp = $anchor
                ->subDays($rowNumber % 30)
                ->setTime($rowNumber % 24, ($rowNumber * 7) % 60)
                ->toDateTimeString();
            $isPaid = $rowNumber % 4 !== 0;
            $transactionNumber = 'PERF-HISTORY-'.$rowNumber;
            $sales[] = [
                'warung_id' => $warung->id,
                'user_id' => $manager->id,
                'created_by_superadmin_id' => null,
                'no_transaksi' => $transactionNumber,
                'nama_pelanggan' => null,
                'idempotency_key' => 'perf-history-sale-'.$rowNumber,
                'payload_hash' => hash('sha256', 'perf-history-sale-'.$rowNumber),
                'idempotency_expires_at' => null,
                'tanggal' => $timestamp,
                'subtotal' => '15000.00',
                'diskon' => '0.00',
                'total' => '15000.00',
                'bayar' => $isPaid ? '15000.00' : '0.00',
                'kembalian' => '0.00',
                'metode_pembayaran' => $isPaid ? ($rowNumber % 2 === 0 ? 'cash' : 'qris') : null,
                'status' => $isPaid ? 'selesai' : 'menunggu_pembayaran',
                'status_pembayaran' => $isPaid ? 'lunas' : 'belum_lunas',
                'dibayar_pada' => $isPaid ? $timestamp : null,
                'pembayaran_user_id' => $isPaid ? $manager->id : null,
                'pembayaran_superadmin_id' => null,
                'pembayaran_idempotency_key' => null,
                'pembayaran_payload_hash' => null,
                'pembayaran_idempotency_expires_at' => null,
                'catatan' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $purchases[] = [
                'warung_id' => $warung->id,
                'user_id' => $manager->id,
                'created_by_superadmin_id' => null,
                'no_transaksi' => $transactionNumber,
                'idempotency_key' => 'perf-history-purchase-'.$rowNumber,
                'payload_hash' => hash('sha256', 'perf-history-purchase-'.$rowNumber),
                'idempotency_expires_at' => null,
                'tanggal' => $timestamp,
                'total' => '5000.00',
                'catatan' => null,
                'status' => 'tercatat',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($sales, 500) as $chunk) {
            DB::table('penjualans')->insert($chunk);
        }

        foreach (array_chunk($purchases, 500) as $chunk) {
            DB::table('pembelians')->insert($chunk);
        }

        $plainTextToken = $manager->createToken('performance-history-benchmark')->plainTextToken;

        $this->command?->info('Seeded 5000 sales and 5000 purchases for warung '.$warung->id.'.');
        $this->command?->line('PERFORMANCE_WARUNG_ID='.$warung->id);
        $this->command?->line('PERFORMANCE_BEARER_TOKEN='.$plainTextToken);
    }
}
