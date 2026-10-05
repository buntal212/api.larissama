<?php

namespace App\Actions\Penjualan;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\CanonicalRequestPayload;
use App\Support\IdempotencyKeyWindow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePenjualan
{
    private const string MAXIMUM_MONEY = '9999999999999.99';

    public function __construct(
        private readonly CanonicalRequestPayload $payloadHasher,
        private readonly IdempotencyKeyWindow $idempotencyKeyWindow,
    ) {}

    /** @param array<string, mixed> $input */
    public function execute(User $actor, string $idempotencyKey, array $input): Penjualan
    {
        $payloadHash = $this->payloadHasher->hash($input);

        try {
            return DB::transaction(function () use ($actor, $idempotencyKey, $input, $payloadHash): Penjualan {
                $existing = $this->findByKey($actor, $idempotencyKey);

                if ($existing !== null) {
                    return $this->replayOrFail($existing, $payloadHash);
                }

                $menuIds = collect($input['rincian'])->pluck('menu_id')->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
                $menus = Menu::query()
                    ->where('warung_id', $actor->warung_id)
                    ->whereIn('id', $menuIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (Menu $menu): int => (int) $menu->getKey());
                $categories = KategoriMenu::query()
                    ->where('warung_id', $actor->warung_id)
                    ->whereIn('id', $menus->pluck('kategori_menu_id')->unique())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (KategoriMenu $category): int => (int) $category->getKey());

                $lineData = [];
                $subtotal = BigDecimal::zero();

                foreach ($input['rincian'] as $index => $line) {
                    $menuId = (int) $line['menu_id'];
                    $menu = $menus->get($menuId);
                    $category = $menu instanceof Menu ? $categories->get((int) $menu->kategori_menu_id) : null;

                    if (! $menu instanceof Menu || ! $menu->aktif || ! $category instanceof KategoriMenu || ! $category->aktif) {
                        throw ValidationException::withMessages([
                            "rincian.$index.menu_id" => ['Menu harus aktif dan termasuk kategori aktif pada warung ini.'],
                        ]);
                    }

                    $price = BigDecimal::of((string) $menu->harga);
                    $quantity = BigDecimal::of((string) $line['qty']);
                    $lineDiscount = BigDecimal::of((string) ($line['diskon'] ?? '0.00'));
                    $gross = $price->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);

                    if ($lineDiscount->compareTo($gross) > 0) {
                        throw ValidationException::withMessages([
                            "rincian.$index.diskon" => ['Diskon rincian tidak boleh melebihi nilai menu dan kuantitas.'],
                        ]);
                    }

                    $lineSubtotal = $gross->minus($lineDiscount)->toScale(2, RoundingMode::HalfUp);
                    $this->assertDatabaseMoney($lineSubtotal, "rincian.$index.diskon");
                    $subtotal = $subtotal->plus($lineSubtotal);
                    $this->assertDatabaseMoney($subtotal, 'rincian');
                    $lineData[] = [
                        'warung_id' => $actor->warung_id,
                        'menu_id' => $menuId,
                        'nama_menu' => $menu->nama,
                        'harga' => (string) $price->toScale(2),
                        'qty' => (string) $quantity->toScale(2),
                        'diskon' => (string) $lineDiscount->toScale(2),
                        'subtotal' => (string) $lineSubtotal,
                        'catatan' => $line['catatan'] ?? null,
                    ];
                }

                $headerDiscount = BigDecimal::of((string) ($input['diskon'] ?? '0.00'));

                if ($headerDiscount->compareTo($subtotal) > 0) {
                    throw ValidationException::withMessages([
                        'diskon' => ['Diskon header tidak boleh melebihi subtotal penjualan.'],
                    ]);
                }

                $total = $subtotal->minus($headerDiscount)->toScale(2, RoundingMode::HalfUp);
                $this->assertDatabaseMoney($total, 'diskon');
                $paid = BigDecimal::of((string) $input['bayar']);
                $paymentMethod = (string) $input['metode_pembayaran'];

                if ($paymentMethod === 'cash') {
                    if ($paid->compareTo($total) < 0) {
                        throw ValidationException::withMessages([
                            'bayar' => ['Pembayaran tunai harus sama dengan atau lebih besar dari total.'],
                        ]);
                    }

                    $change = $paid->minus($total)->toScale(2, RoundingMode::HalfUp);
                } else {
                    if ($paid->compareTo($total) !== 0) {
                        throw ValidationException::withMessages([
                            'bayar' => ['Pembayaran QRIS/transfer harus sama dengan total.'],
                        ]);
                    }

                    $change = BigDecimal::zero()->toScale(2);
                }

                $sale = Penjualan::query()->create([
                    'warung_id' => $actor->warung_id,
                    'user_id' => $actor->getKey(),
                    'no_transaksi' => 'PJ-'.Str::ulid(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'idempotency_expires_at' => $this->idempotencyKeyWindow->expiresAt(),
                    'tanggal' => CarbonImmutable::parse((string) $input['tanggal'])->utc()->toDateTimeString(),
                    'subtotal' => (string) $subtotal->toScale(2, RoundingMode::HalfUp),
                    'diskon' => (string) $headerDiscount->toScale(2),
                    'total' => (string) $total,
                    'bayar' => (string) $paid->toScale(2),
                    'kembalian' => (string) $change,
                    'metode_pembayaran' => $paymentMethod,
                    'status' => 'selesai',
                    'catatan' => $input['catatan'] ?? null,
                ]);

                $sale->rincian()->createMany($lineData);

                return $sale->load('rincian');
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByKey($actor, $idempotencyKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replayOrFail($existing, $payloadHash);
        }
    }

    private function findByKey(User $actor, string $idempotencyKey): ?Penjualan
    {
        $sale = Penjualan::query()
            ->where('warung_id', $actor->warung_id)
            ->where('user_id', $actor->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($sale === null) {
            return null;
        }

        if ($this->idempotencyKeyWindow->hasExpired($sale->idempotency_expires_at)) {
            $this->idempotencyKeyWindow->release($sale);

            return null;
        }

        return $sale->load('rincian');
    }

    private function replayOrFail(Penjualan $sale, string $payloadHash): Penjualan
    {
        if (! hash_equals($sale->payload_hash, $payloadHash)) {
            throw new IdempotencyKeyConflictException;
        }

        return $sale;
    }

    private function assertDatabaseMoney(BigDecimal $amount, string $field): void
    {
        if ($amount->compareTo(BigDecimal::of(self::MAXIMUM_MONEY)) > 0) {
            throw ValidationException::withMessages([
                $field => ['Nominal melebihi kapasitas DECIMAL(15,2).'],
            ]);
        }
    }
}
