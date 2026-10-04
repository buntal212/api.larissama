<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Penjualan\CreatePenjualan;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PenjualanIndexRequest;
use App\Http\Requests\Api\V1\PenjualanStoreRequest;
use App\Http\Resources\Api\V1\PenjualanResource;
use App\Http\Resources\Api\V1\PenjualanSummaryResource;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Penjualan;
use App\Models\User;
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

        if ($actor->role === 'kasir') {
            $query->where('user_id', $actor->getKey());
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'], $filters['date_to'])) {
            $timezone = (string) $actor->warung()->value('timezone');
            [$startUtc, $endExclusiveUtc] = $periodBounds->utcBounds($filters['date_from'], $filters['date_to'], $timezone);
            $query->where('tanggal', '>=', $startUtc)->where('tanggal', '<', $endExclusiveUtc);
        }

        $direction = ($filters['sort'] ?? '-tanggal') === '-tanggal' ? 'desc' : 'asc';
        $paginator = $query
            ->orderBy('tanggal', $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));

        return ApiPaginationResponse::make($paginator, PenjualanSummaryResource::class, $request);
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

        return response()->json(['data' => (new PenjualanResource($sale))->resolve($request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', Penjualan::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $query = Penjualan::query()->where('warung_id', $actor->warung_id)->with('rincian');

        if ($actor->role === 'kasir') {
            $query->where('user_id', $actor->getKey());
        }

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
}
