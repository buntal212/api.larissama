<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pembelian\CreatePembelian;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PembelianIndexRequest;
use App\Http\Requests\Api\V1\PembelianStoreRequest;
use App\Http\Resources\Api\V1\PembelianResource;
use App\Http\Resources\Api\V1\PembelianSummaryResource;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Pembelian;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\PeriodBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PembelianController extends Controller
{
    public function index(PembelianIndexRequest $request, PeriodBounds $periodBounds): JsonResponse
    {
        Gate::authorize('viewAny', Pembelian::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $query = Pembelian::query()->where('warung_id', $actor->warung_id);

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

        return ApiPaginationResponse::make($paginator, PembelianSummaryResource::class, $request, $page);
    }

    public function store(PembelianStoreRequest $request, CreatePembelian $createPembelian): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        if ($idempotencyKey instanceof JsonResponse) {
            return $idempotencyKey;
        }

        try {
            $purchase = $createPembelian->execute($actor, $idempotencyKey, $request->validated());
        } catch (IdempotencyKeyConflictException) {
            return ApiErrorResponse::make(
                'IDEMPOTENCY_KEY_REUSED',
                'Idempotency-Key sudah digunakan untuk payload berbeda.',
                409,
            );
        }

        return response()->json(['data' => (new PembelianResource($purchase))->resolve($request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', Pembelian::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $purchase = Pembelian::query()
            ->where('warung_id', $actor->warung_id)
            ->with('rincian')
            ->findOrFail($id);
        Gate::authorize('view', $purchase);

        return response()->json(['data' => (new PembelianResource($purchase))->resolve($request)]);
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
