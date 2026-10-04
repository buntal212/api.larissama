<?php

namespace App\Actions\Laporan;

use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\PeriodBounds;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class BuildLaporanPeriode
{
    public function __construct(private readonly PeriodBounds $periodBounds) {}

    /** @return array<string, mixed> */
    public function penjualan(User $actor, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) $actor->warung()->value('timezone');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $totals = Penjualan::query()
            ->where('warung_id', $actor->warung_id)
            ->where('status', 'selesai')
            ->where('tanggal', '>=', $startUtc)
            ->where('tanggal', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_transaksi, COALESCE(SUM(total), 0) AS total_pendapatan')
            ->firstOrFail();

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'jumlah_transaksi' => (int) $totals->jumlah_transaksi,
            'total_pendapatan' => (string) BigDecimal::of((string) $totals->total_pendapatan)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /** @return array<string, mixed> */
    public function pembelian(User $actor, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) $actor->warung()->value('timezone');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $totals = Pembelian::query()
            ->where('warung_id', $actor->warung_id)
            ->where('tanggal', '>=', $startUtc)
            ->where('tanggal', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_transaksi, COALESCE(SUM(total), 0) AS total_pembelian')
            ->firstOrFail();

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'jumlah_transaksi' => (int) $totals->jumlah_transaksi,
            'total_pembelian' => (string) BigDecimal::of((string) $totals->total_pembelian)->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
