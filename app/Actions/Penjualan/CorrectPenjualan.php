<?php

namespace App\Actions\Penjualan;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PenjualanStateConflictException;
use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\PenjualanKoreksi;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use App\Support\IdempotencyKeyWindow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectPenjualan
{
    private const string MAXIMUM_MONEY = '9999999999999.99';

    public function __construct(
        private readonly CanonicalRequestPayload $payloadHasher,
        private readonly IdempotencyKeyWindow $idempotencyKeyWindow,
    ) {}

    /** @param array<string, mixed> $input */
    public function update(User $actor, int $saleId, string $key, array $input): PenjualanKoreksi
    {
        return $this->change($actor, $saleId, 'ubah', $key, $input);
    }

    /** @param array<string, mixed> $input */
    public function cancel(User $actor, int $saleId, string $key, array $input): PenjualanKoreksi
    {
        return $this->change($actor, $saleId, 'batalkan', $key, $input);
    }

    /** @param array<string, mixed> $input */
    private function change(User $actor, int $saleId, string $kind, string $key, array $input): PenjualanKoreksi
    {
        $hash = $this->payloadHasher->hash([
            'penjualan_id' => (string) $saleId,
            'jenis' => $kind,
            'request' => $input,
        ]);

        try {
            return DB::transaction(function () use ($actor, $saleId, $kind, $key, $input, $hash): PenjualanKoreksi {
                $existing = $this->findByKey($actor, $kind, $key);

                if ($existing instanceof PenjualanKoreksi) {
                    return $this->replayOrFail($existing, $hash);
                }

                $sale = Penjualan::query()
                    ->where('warung_id', $actor->warung_id)
                    ->lockForUpdate()
                    ->with('rincian', 'retur')
                    ->findOrFail($saleId);
                $existing = $this->findByKey($actor, $kind, $key);

                if ($existing instanceof PenjualanKoreksi) {
                    return $this->replayOrFail($existing, $hash);
                }

                $this->assertEditable($sale);
                $before = $this->snapshot($sale);
                $after = $kind === 'batalkan'
                    ? $this->cancelSale($sale, $before)
                    : $this->updateSale($sale, $before, $input);

                return PenjualanKoreksi::query()->create([
                    'warung_id' => $actor->warung_id,
                    'penjualan_id' => $sale->getKey(),
                    'user_id' => $actor->getKey(),
                    'jenis' => $kind,
                    'alasan' => $input['alasan'],
                    'sebelum' => $before,
                    'sesudah' => $after,
                    'idempotency_key' => $key,
                    'payload_hash' => $hash,
                    'idempotency_expires_at' => $this->idempotencyKeyWindow->expiresAt(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByKey($actor, $kind, $key);

            if (! $existing instanceof PenjualanKoreksi) {
                throw $exception;
            }

            return $this->replayOrFail($existing, $hash);
        }
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function updateSale(Penjualan $sale, array $before, array $input): array
    {
        $after = $before;

        if (array_key_exists('tanggal', $input)) {
            $after['tanggal'] = CarbonImmutable::parse((string) $input['tanggal'])->utc()->toISOString();
        }
        if (array_key_exists('catatan', $input)) {
            $after['catatan'] = $input['catatan'];
        }

        $lineData = null;
        if (array_key_exists('rincian', $input)) {
            [$lineData, $subtotal] = $this->calculateLines($input['rincian'], (int) $sale->warung_id);
            $after['rincian'] = array_map(static fn (array $line): array => [
                'menu_id' => (string) $line['menu_id'],
                'nama_menu' => $line['nama_menu'],
                'harga' => $line['harga'],
                'qty' => $line['qty'],
                'diskon' => $line['diskon'],
                'subtotal' => $line['subtotal'],
                'catatan' => $line['catatan'],
            ], $lineData);
            $after['subtotal'] = $subtotal;
        }

        $subtotal = BigDecimal::of($after['subtotal']);
        $discount = BigDecimal::of((string) ($input['diskon'] ?? $after['diskon']));
        if ($discount->compareTo($subtotal) > 0) {
            throw ValidationException::withMessages(['diskon' => ['Diskon header tidak boleh melebihi subtotal penjualan.']]);
        }
        $total = $subtotal->minus($discount)->toScale(2, RoundingMode::HalfUp);
        $this->assertDatabaseMoney($total, 'diskon');
        $paymentMethod = (string) ($input['metode_pembayaran'] ?? $after['metode_pembayaran']);
        $paid = BigDecimal::of((string) ($input['bayar'] ?? $after['bayar']));
        $change = $this->calculateChange($paymentMethod, $paid, $total);

        $after['diskon'] = (string) $discount->toScale(2, RoundingMode::HalfUp);
        $after['total'] = (string) $total;
        $after['bayar'] = (string) $paid->toScale(2, RoundingMode::HalfUp);
        $after['kembalian'] = (string) $change;
        $after['metode_pembayaran'] = $paymentMethod;

        if ($before === $after) {
            throw ValidationException::withMessages(['alasan' => ['Tidak ada perubahan data untuk dicatat.']]);
        }

        $sale->forceFill([
            'tanggal' => CarbonImmutable::parse((string) $after['tanggal'])->utc()->toDateTimeString(),
            'subtotal' => (string) $subtotal,
            'diskon' => $after['diskon'],
            'total' => $after['total'],
            'bayar' => $after['bayar'],
            'kembalian' => $after['kembalian'],
            'metode_pembayaran' => $after['metode_pembayaran'],
            'catatan' => $after['catatan'],
        ])->save();

        if ($lineData !== null) {
            $sale->rincian()->delete();
            $sale->rincian()->createMany($lineData);
        }

        return $after;
    }

    /** @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function cancelSale(Penjualan $sale, array $before): array
    {
        if ($sale->status !== 'selesai') {
            throw new PenjualanStateConflictException('PENJUALAN_TIDAK_AKTIF', 'Hanya penjualan yang belum dibatalkan atau diretur yang dapat dibatalkan.');
        }

        $sale->forceFill(['status' => 'batal'])->save();

        return [...$before, 'status' => 'batal'];
    }

    private function assertEditable(Penjualan $sale): void
    {
        if ($sale->status !== 'selesai') {
            throw new PenjualanStateConflictException('PENJUALAN_TIDAK_AKTIF', 'Penjualan yang sudah dibatalkan atau diretur tidak dapat dikoreksi.');
        }

        if ($sale->retur->isNotEmpty()) {
            throw new PenjualanStateConflictException('PENJUALAN_SUDAH_DIRETUR', 'Penjualan yang telah memiliki retur tidak dapat dikoreksi atau dibatalkan.');
        }

        if ($sale->created_at === null || CarbonImmutable::parse($sale->created_at)->utc()->addHours(72)->isPast()) {
            throw new PenjualanStateConflictException('BATAS_KOREKSI_TERLEWATI', 'Batas koreksi penjualan 72 jam telah terlewati; catat retur bila diperlukan.');
        }
    }

    /** @param array<int, array<string, mixed>> $lines
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function calculateLines(array $lines, int $warungId): array
    {
        $menuIds = collect($lines)->pluck('menu_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        $menus = Menu::query()->where('warung_id', $warungId)->whereIn('id', $menuIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $categories = KategoriMenu::query()->where('warung_id', $warungId)->whereIn('id', $menus->pluck('kategori_menu_id')->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $data = [];
        $subtotal = BigDecimal::zero();

        foreach ($lines as $index => $line) {
            $menu = $menus->get((int) $line['menu_id']);
            $category = $menu instanceof Menu ? $categories->get((int) $menu->kategori_menu_id) : null;
            if (! $menu instanceof Menu || ! $menu->aktif || ! $category instanceof KategoriMenu || ! $category->aktif) {
                throw ValidationException::withMessages(["rincian.$index.menu_id" => ['Menu harus aktif dan termasuk kategori aktif pada warung ini.']]);
            }

            $price = BigDecimal::of((string) $menu->harga);
            $quantity = BigDecimal::of((string) $line['qty']);
            $discount = BigDecimal::of((string) ($line['diskon'] ?? '0.00'));
            $gross = $price->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
            if ($discount->compareTo($gross) > 0) {
                throw ValidationException::withMessages(["rincian.$index.diskon" => ['Diskon rincian tidak boleh melebihi nilai menu dan kuantitas.']]);
            }
            $lineSubtotal = $gross->minus($discount)->toScale(2, RoundingMode::HalfUp);
            $this->assertDatabaseMoney($lineSubtotal, "rincian.$index.diskon");
            $subtotal = $subtotal->plus($lineSubtotal);
            $this->assertDatabaseMoney($subtotal, 'rincian');
            $data[] = [
                'warung_id' => $warungId,
                'menu_id' => $menu->getKey(),
                'nama_menu' => $menu->nama,
                'harga' => (string) $price->toScale(2),
                'qty' => (string) $quantity->toScale(2),
                'diskon' => (string) $discount->toScale(2),
                'subtotal' => (string) $lineSubtotal,
                'catatan' => $line['catatan'] ?? null,
            ];
        }

        return [$data, (string) $subtotal->toScale(2, RoundingMode::HalfUp)];
    }

    private function calculateChange(string $method, BigDecimal $paid, BigDecimal $total): string
    {
        if ($method === 'cash') {
            if ($paid->compareTo($total) < 0) {
                throw ValidationException::withMessages(['bayar' => ['Pembayaran tunai harus sama dengan atau lebih besar dari total.']]);
            }

            return (string) $paid->minus($total)->toScale(2, RoundingMode::HalfUp);
        }

        if ($paid->compareTo($total) !== 0) {
            throw ValidationException::withMessages(['bayar' => ['Pembayaran QRIS/transfer harus sama dengan total.']]);
        }

        return '0.00';
    }

    /** @return array<string, mixed> */
    private function snapshot(Penjualan $sale): array
    {
        return [
            'status' => $sale->status,
            'tanggal' => $sale->tanggal?->utc()->toISOString(),
            'subtotal' => (string) $sale->subtotal,
            'diskon' => (string) $sale->diskon,
            'total' => (string) $sale->total,
            'bayar' => (string) $sale->bayar,
            'kembalian' => (string) $sale->kembalian,
            'metode_pembayaran' => $sale->metode_pembayaran,
            'catatan' => $sale->catatan,
            'rincian' => $sale->rincian->map(static fn ($line): array => [
                'menu_id' => (string) $line->menu_id,
                'nama_menu' => $line->nama_menu,
                'harga' => $line->harga,
                'qty' => $line->qty,
                'diskon' => $line->diskon,
                'subtotal' => $line->subtotal,
                'catatan' => $line->catatan,
            ])->all(),
        ];
    }

    private function findByKey(User $actor, string $kind, string $key): ?PenjualanKoreksi
    {
        $event = PenjualanKoreksi::query()->where('warung_id', $actor->warung_id)->where('user_id', $actor->getKey())
            ->where('jenis', $kind)->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($event !== null && $this->idempotencyKeyWindow->hasExpired($event->idempotency_expires_at)) {
            $this->idempotencyKeyWindow->release($event);

            return null;
        }

        return $event;
    }

    private function replayOrFail(PenjualanKoreksi $event, string $hash): PenjualanKoreksi
    {
        if (! hash_equals((string) $event->payload_hash, $hash)) {
            throw new IdempotencyKeyConflictException;
        }

        return $event;
    }

    private function assertDatabaseMoney(BigDecimal $amount, string $field): void
    {
        if ($amount->compareTo(BigDecimal::of(self::MAXIMUM_MONEY)) > 0) {
            throw ValidationException::withMessages([$field => ['Nominal melebihi kapasitas DECIMAL(15,2).']]);
        }
    }
}
