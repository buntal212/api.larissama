<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pembelian\CorrectPembelian;
use App\Actions\Pembelian\CreatePembelian;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PembelianStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PembelianCancelRequest;
use App\Http\Requests\Api\V1\PembelianIndexRequest;
use App\Http\Requests\Api\V1\PembelianStoreRequest;
use App\Http\Requests\Api\V1\PembelianUpdateRequest;
use App\Http\Requests\Api\V1\TenantReadRequest;
use App\Http\Resources\Api\V1\PembelianKoreksiResource;
use App\Http\Resources\Api\V1\PembelianResource;
use App\Http\Resources\Api\V1\PembelianSummaryResource;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Pembelian;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\PeriodBounds;
use App\Support\TenantReadScope;
use App\Support\TransactionWriteScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PembelianController extends Controller
{
    public function index(PembelianIndexRequest $request, PeriodBounds $periodBounds, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', Pembelian::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $warungId = $tenantReadScope->resolve($actor, $filters);
        $query = Pembelian::query()->where('warung_id', $warungId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'], $filters['date_to'])) {
            $timezone = $tenantReadScope->timezone($warungId);
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

        return ApiPaginationResponse::make($paginator, PembelianSummaryResource::class, $request, $page);
    }

    public function store(PembelianStoreRequest $request, CreatePembelian $createPembelian, TransactionWriteScope $writeScope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $input = $request->validated();
        $warungId = $writeScope->resolve($actor, $input);
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        if ($idempotencyKey instanceof JsonResponse) {
            return $idempotencyKey;
        }

        try {
            $purchase = $createPembelian->execute($actor, $idempotencyKey, $input, $warungId);
        } catch (IdempotencyKeyConflictException) {
            return ApiErrorResponse::make(
                'IDEMPOTENCY_KEY_REUSED',
                'Idempotency-Key sudah digunakan untuk payload berbeda.',
                409,
            );
        }

        return response()->json(['data' => (new PembelianResource($purchase))->resolve($request)], 201);
    }

    public function show(TenantReadRequest $request, string $id, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', Pembelian::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $warungId = $tenantReadScope->resolve($actor, $request->validated());
        $purchase = Pembelian::query()
            ->where('warung_id', $warungId)
            ->with('rincian', 'koreksi')
            ->findOrFail($id);
        Gate::authorize('view', [$purchase, $warungId]);

        return response()->json(['data' => (new PembelianResource($purchase))->resolve($request)]);
    }

    public function update(PembelianUpdateRequest $request, string $id, CorrectPembelian $correctPembelian, TransactionWriteScope $writeScope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $input = $request->validated();
        $warungId = $writeScope->resolve($actor, $input);
        $purchase = Pembelian::query()->where('warung_id', $warungId)->findOrFail($id);
        Gate::authorize('update', [$purchase, (string) $warungId]);
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        if ($idempotencyKey instanceof JsonResponse) {
            return $idempotencyKey;
        }

        try {
            $correction = $correctPembelian->update($actor, (int) $id, $idempotencyKey, $input, $warungId);
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PembelianStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PembelianKoreksiResource($correction))->resolve($request)], 201);
    }

    public function cancel(PembelianCancelRequest $request, string $id, CorrectPembelian $correctPembelian, TransactionWriteScope $writeScope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $input = $request->validated();
        $warungId = $writeScope->resolve($actor, $input);
        $purchase = Pembelian::query()->where('warung_id', $warungId)->findOrFail($id);
        Gate::authorize('cancel', [$purchase, (string) $warungId]);
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        if ($idempotencyKey instanceof JsonResponse) {
            return $idempotencyKey;
        }

        try {
            $correction = $correctPembelian->cancel($actor, (int) $id, $idempotencyKey, $input, $warungId);
        } catch (IdempotencyKeyConflictException) {
            return $this->idempotencyConflict();
        } catch (PembelianStateConflictException $exception) {
            return ApiErrorResponse::make($exception->errorCode, $exception->getMessage(), 409);
        }

        return response()->json(['data' => (new PembelianKoreksiResource($correction))->resolve($request)], 201);
    }

    private function idempotencyConflict(): JsonResponse
    {
        return ApiErrorResponse::make(
            'IDEMPOTENCY_KEY_REUSED',
            'Idempotency-Key sudah digunakan untuk payload berbeda.',
            409,
        );
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
}
