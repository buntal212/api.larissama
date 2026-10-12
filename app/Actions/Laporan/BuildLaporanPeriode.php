<?php

namespace App\Actions\Laporan;

use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PenjualanRetur;
use App\Models\PenjualanRinci;
use App\Models\Warung;
use App\Support\PeriodBounds;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

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
        $paymentMethods = Penjualan::query()
            ->where('warung_id', $warungId)
            ->whereIn('status', ['selesai', 'diretur_sebagian', 'diretur_penuh'])
            ->where('status_pembayaran', 'lunas')
            ->where('dibayar_pada', '>=', $startUtc)
            ->where('dibayar_pada', '<', $endExclusiveUtc)
            ->selectRaw('metode_pembayaran, COUNT(*) AS jumlah_transaksi, COALESCE(SUM(total), 0) AS total_penjualan')
            ->groupBy('metode_pembayaran')
            ->orderBy('metode_pembayaran')
            ->get();
        $returns = PenjualanRetur::query()
            ->where('warung_id', $warungId)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_retur, COALESCE(SUM(nominal), 0) AS total_retur')
            ->firstOrFail();
        $gross = BigDecimal::of((string) $sales->total_penjualan)->toScale(2, RoundingMode::HalfUp);
        $returned = BigDecimal::of((string) $returns->total_retur)->toScale(2, RoundingMode::HalfUp);
        $net = $gross->minus($returned)->toScale(2, RoundingMode::HalfUp);
        $average = (int) $sales->jumlah_transaksi === 0
            ? BigDecimal::zero()->toScale(2)
            : $gross->dividedBy((int) $sales->jumlah_transaksi, 2, RoundingMode::HalfUp);

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'jumlah_transaksi' => (int) $sales->jumlah_transaksi,
            'total_penjualan' => (string) $gross,
            'rata_rata_transaksi' => (string) $average,
            'per_metode_pembayaran' => $paymentMethods->map(static fn ($row): array => [
                'metode_pembayaran' => (string) $row->metode_pembayaran,
                'jumlah_transaksi' => (int) $row->jumlah_transaksi,
                'total_penjualan' => (string) BigDecimal::of((string) $row->total_penjualan)->toScale(2, RoundingMode::HalfUp),
            ])->all(),
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

    /** @return array<string, mixed> */
    public function pesananBelumLunas(string $warungId, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $totals = Penjualan::query()
            ->where('warung_id', $warungId)
            ->where('status', 'menunggu_pembayaran')
            ->where('status_pembayaran', 'belum_lunas')
            ->where('tanggal', '>=', $startUtc)
            ->where('tanggal', '<', $endExclusiveUtc)
            ->selectRaw('COUNT(*) AS jumlah_pesanan, COALESCE(SUM(total), 0) AS total_belum_lunas')
            ->firstOrFail();

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'jumlah_pesanan' => (int) $totals->jumlah_pesanan,
            'total_belum_lunas' => (string) BigDecimal::of((string) $totals->total_belum_lunas)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /** @return array<string, mixed> */
    public function penjualanHarian(string $warungId, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $days = [];

        $sales = Penjualan::query()
            ->where('warung_id', $warungId)
            ->whereIn('status', ['selesai', 'diretur_sebagian', 'diretur_penuh'])
            ->where('status_pembayaran', 'lunas')
            ->where('dibayar_pada', '>=', $startUtc)
            ->where('dibayar_pada', '<', $endExclusiveUtc)
            ->select(['dibayar_pada', 'total'])
            ->orderBy('dibayar_pada')
            ->cursor();

        foreach ($sales as $sale) {
            $date = CarbonImmutable::parse($sale->dibayar_pada, 'UTC')->setTimezone($timezone)->toDateString();
            $days[$date] ??= $this->emptySalesDay($date);
            $days[$date]['jumlah_transaksi']++;
            $days[$date]['total_penjualan'] = $days[$date]['total_penjualan']->plus(BigDecimal::of((string) $sale->total));
        }

        $returns = PenjualanRetur::query()
            ->where('warung_id', $warungId)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->select(['created_at', 'nominal'])
            ->orderBy('created_at')
            ->cursor();

        foreach ($returns as $return) {
            $date = CarbonImmutable::parse($return->created_at, 'UTC')->setTimezone($timezone)->toDateString();
            $days[$date] ??= $this->emptySalesDay($date);
            $days[$date]['jumlah_retur']++;
            $days[$date]['total_retur'] = $days[$date]['total_retur']->plus(BigDecimal::of((string) $return->nominal));
        }

        ksort($days);

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'data' => array_values(array_map(static fn (array $day): array => [
                'tanggal' => $day['tanggal'],
                'jumlah_transaksi' => $day['jumlah_transaksi'],
                'total_penjualan' => (string) $day['total_penjualan']->toScale(2, RoundingMode::HalfUp),
                'jumlah_retur' => $day['jumlah_retur'],
                'total_retur' => (string) $day['total_retur']->toScale(2, RoundingMode::HalfUp),
                'total_pendapatan' => (string) $day['total_penjualan']->minus($day['total_retur'])->toScale(2, RoundingMode::HalfUp),
            ], $days)),
        ];
    }

    /** @return array<string, mixed> */
    public function penjualanItem(string $warungId, string $dateFrom, string $dateTo): array
    {
        $timezone = (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
        [$startUtc, $endExclusiveUtc] = $this->periodBounds->utcBounds($dateFrom, $dateTo, $timezone);
        $items = PenjualanRinci::query()
            ->join('penjualans', function ($join): void {
                $join->on('penjualans.warung_id', '=', 'penjualan_rincis.warung_id')
                    ->on('penjualans.id', '=', 'penjualan_rincis.penjualan_id');
            })
            ->where('penjualan_rincis.warung_id', $warungId)
            ->whereIn('penjualans.status', ['selesai', 'diretur_sebagian', 'diretur_penuh'])
            ->where('penjualans.status_pembayaran', 'lunas')
            ->where('penjualans.dibayar_pada', '>=', $startUtc)
            ->where('penjualans.dibayar_pada', '<', $endExclusiveUtc)
            ->selectRaw('penjualan_rincis.menu_id, penjualan_rincis.nama_menu, penjualan_rincis.harga, SUM(penjualan_rincis.qty) AS qty_terjual, SUM(penjualan_rincis.subtotal) AS nilai_item')
            ->groupBy('penjualan_rincis.menu_id', 'penjualan_rincis.nama_menu', 'penjualan_rincis.harga')
            ->orderBy('penjualan_rincis.nama_menu')
            ->orderBy('penjualan_rincis.harga')
            ->get();

        return [
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'timezone' => $timezone],
            'data' => $items->map(static fn ($item): array => [
                'jenis_item' => $item->menu_id === null ? 'bebas' : 'menu',
                'menu_id' => $item->menu_id === null ? null : (string) $item->menu_id,
                'nama_item' => (string) $item->nama_menu,
                'harga_saat_transaksi' => (string) BigDecimal::of((string) $item->harga)->toScale(2, RoundingMode::HalfUp),
                'qty_terjual' => (string) BigDecimal::of((string) $item->qty_terjual)->toScale(2, RoundingMode::HalfUp),
                'nilai_item' => (string) BigDecimal::of((string) $item->nilai_item)->toScale(2, RoundingMode::HalfUp),
            ])->all(),
        ];
    }

    /** @return array{tanggal: string, jumlah_transaksi: int, total_penjualan: BigDecimal, jumlah_retur: int, total_retur: BigDecimal} */
    private function emptySalesDay(string $date): array
    {
        return [
            'tanggal' => $date,
            'jumlah_transaksi' => 0,
            'total_penjualan' => BigDecimal::zero()->toScale(2),
            'jumlah_retur' => 0,
            'total_retur' => BigDecimal::zero()->toScale(2),
        ];
    }
}
