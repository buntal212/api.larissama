<?php

namespace App\Actions\Laporan;

use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PenjualanRetur;
use App\Models\Warung;
use App\Support\PeriodBounds;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class BuildLaporanPeriode
{
    public function __construct(private readonly PeriodBounds $periodBounds) {}

    /** @return array<string, mixed> */
    public function penjualan(string $warungId, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $sales = Penjualan::query()
            ->where('warung_id', $warungId)
            ->whereIn('status', ['selesai', 'diretur_sebagian', 'diretur_penuh'])
            ->where('status_pembayaran', 'lunas')
            ->where('dibayar_pada', '>=', $startUtc)
            ->where('dibayar_pada', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_transaksi, COALESCE(SUM(total), 0) AS total_penjualan')
            ->firstOrFail();
        $returns = PenjualanRetur::query()
            ->where('warung_id', $warungId)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_retur, COALESCE(SUM(nominal), 0) AS total_retur')
            ->firstOrFail();
        $gross = BigDecimal::of((string) $sales->total_penjualan)->toScale(2, RoundingMode::HalfUp);
        $returned = BigDecimal::of((string) $returns->total_retur)->toScale(2, RoundingMode::HalfUp);
        $net = $gross->minus($returned)->toScale(2, RoundingMode::HalfUp);

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'jumlah_transaksi' => (int) $sales->jumlah_transaksi,
            'total_penjualan' => (string) $gross,
            'jumlah_retur' => (int) $returns->jumlah_retur,
            'total_retur' => (string) $returned,
            'total_pendapatan' => (string) $net,
        ];
    }

    /** @return array<string, mixed> */
    public function pembelian(string $warungId, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $totals = Pembelian::query()
            ->where('warung_id', $warungId)
            ->where('status', 'tercatat')
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
