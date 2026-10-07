<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Penjualan\CorrectPenjualan;
use App\Actions\Penjualan\CreatePenjualan;
use App\Actions\Penjualan\RecordPenjualanPayment;
use App\Actions\Penjualan\RecordPenjualanRetur;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PenjualanStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PenjualanCancelRequest;
use App\Http\Requests\Api\V1\PenjualanIndexRequest;
use App\Http\Requests\Api\V1\PenjualanKoreksiRequest;
use App\Http\Requests\Api\V1\PenjualanPembayaranRequest;
use App\Http\Requests\Api\V1\PenjualanReturRequest;
use App\Http\Requests\Api\V1\PenjualanStoreRequest;
use App\Http\Resources\Api\V1\PenjualanKoreksiResource;
use App\Http\Resources\Api\V1\PenjualanResource;
use App\Http\Resources\Api\V1\PenjualanReturResource;
use App\Http\Resources\Api\V1\PenjualanSummaryResource;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\PeriodBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PenjualanController extends Controller
{
    public function index(PenjualanIndexRequest $request, PeriodBounds $periodBounds): JsonResponse
    {
        Gate::authorize('viewAny', Penjualan::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $query = Penjualan::query()->where('warung_id', $actor->warung_id);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['status_pembayaran'])) {
            $query->where('status_pembayaran', $filters['status_pembayaran']);
            if ($filters['status_pembayaran'] === 'belum_lunas') {
                $query->where('status', 'menunggu_pembayaran');
            }
        }

        if (isset($filters['date_from'], $filters['date_to'])) {
            $timezone = (string) ($actor->warung?->timezone ?? '');
            [$startUtc, $endExclusiveUtc] = $periodBounds->utcBounds($filters['date_from'], $filters['date_to'], $timezone);
            $query->where('tanggal', '>=', $startUtc)->where('tanggal', '<', $endExclusiveUtc);
        }

        $direction = ($filters['sort'] ?? '-tanggal') === '-tanggal' ? 'desc' : 'asc';
        $page = $filters['page'] ?? '1';
        $paginator = ApiPagination::paginate(
            $query->orderBy('tanggal', $direction)->orderBy('id', $direction),
            (int) ($filters['per_page'] ?? 20),
            $page,
        );

        return ApiPaginationResponse::make($paginator, PenjualanSummaryResource::class, $request, $page);
    }

    public function store(PenjualanStoreRequest $request, CreatePenjualan $createPenjualan): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        if ($idempotencyKey instanceof JsonResponse) {
            return $idempotencyKey;
        }

        try {
            $sale = $createPenjualan->execute($actor, $idempotencyKey, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return ApiErrorResponse::make(
                'IDEMPOTENCY_KEY_REUSED',
                'Idempotency-Key sudah digunakan untuk payload berbeda.',
                409,
            );
        }

        $sale->load('rincian', 'koreksi', 'retur');

        return response()->json(['data' => (new PenjualanResource($sale))->resolve($request)], 201);
    }

    public function update(PenjualanKoreksiRequest $request, string $id, CorrectPenjualan $correctPenjualan): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $sale = Penjualan::query()->where('warung_id', $actor->warung_id)->findOrFail($id);
        Gate::authorize('update', $sale);
        $key = $this->validatedIdempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }

        try {
            $event = $correctPenjualan->update($actor, (int) $id, $key, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PenjualanStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PenjualanKoreksiResource($event))->resolve($request)], 201);
    }

    public function pay(PenjualanPembayaranRequest $request, string $id, RecordPenjualanPayment $recordPayment): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $sale = Penjualan::query()->where('warung_id', $actor->warung_id)->findOrFail($id);
        Gate::authorize('pay', $sale);
        $key = $this->validatedIdempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }

        try {
            $sale = $recordPayment->execute($actor, (int) $id, $key, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PenjualanStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PenjualanResource($sale))->resolve($request)], 201);
    }

    public function cancel(PenjualanCancelRequest $request, string $id, CorrectPenjualan $correctPenjualan): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $sale = Penjualan::query()->where('warung_id', $actor->warung_id)->findOrFail($id);
        Gate::authorize('update', $sale);
        $key = $this->validatedIdempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }

        try {
            $event = $correctPenjualan->cancel($actor, (int) $id, $key, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PenjualanStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PenjualanKoreksiResource($event))->resolve($request)], 201);
    }

    public function storeReturn(PenjualanReturRequest $request, string $id, RecordPenjualanRetur $recordReturn): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $sale = Penjualan::query()->where('warung_id', $actor->warung_id)->findOrFail($id);
        Gate::authorize('manageCorrections', $sale);
        $key = $this->validatedIdempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }

        try {
            $event = $recordReturn->execute($actor, (int) $id, $key, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PenjualanStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PenjualanReturResource($event))->resolve($request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', Penjualan::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $query = Penjualan::query()->where('warung_id', $actor->warung_id)->with('rincian', 'koreksi', 'retur');

        $sale = $query->findOrFail($id);
        Gate::authorize('view', $sale);

        return response()->json(['data' => (new PenjualanResource($sale))->resolve($request)]);
    }

    private function validatedIdempotencyKey(Request $request): string|JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || strlen($key) > 255 || trim($key) !== $key) {
            return ApiErrorResponse::make(
                'VALIDATION_ERROR',
                'Header Idempotency-Key wajib diisi dan maksimal 255 karakter.',
                422,
                ['Idempotency-Key' => ['Isi header dengan 1 sampai 255 karakter tanpa spasi di awal/akhir.']],
            );
        }

        return $key;
    }

    private function idempotencyConflict(): JsonResponse
    {
        return ApiErrorResponse::make(
            'IDEMPOTENCY_KEY_REUSED',
            'Idempotency-Key sudah digunakan untuk payload berbeda.',
            409,
        );
    }
}
