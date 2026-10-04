<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\ProvisionWarung;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminWarungIndexRequest;
use App\Http\Requests\Api\V1\WarungStoreRequest;
use App\Http\Requests\Api\V1\WarungUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\WarungResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Warung;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminWarungController extends Controller
{
    public function index(AdminWarungIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Warung::class);

        $filters = $request->validated();
        $query = Warung::query();
        $search = $filters['q'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('kode', 'like', '%'.$search.'%');
            });
        }

        $active = $this->activeFilter($filters);

        if ($active !== null) {
            $query->where('aktif', $active);
        }

        $sort = $filters['sort'] ?? 'nama';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $page = $filters['page'] ?? '1';
        $paginator = ApiPagination::paginate(
            $query->orderBy($column, $direction)->orderBy('id', $direction),
            (int) ($filters['per_page'] ?? 20),
            $page,
        );

        return ApiPaginationResponse::make($paginator, WarungResource::class, $request, $page);
    }

    public function store(WarungStoreRequest $request, ProvisionWarung $provisionWarung): JsonResponse
    {
        $result = $provisionWarung->execute($request->validated());

        return response()->json([
            'data' => [
                'warung' => (new WarungResource($result['warung']))->resolve($request),
                'owner' => (new UserResource($result['owner']))->resolve($request),
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $warung = Warung::query()->findOrFail($id);
        Gate::authorize('view', $warung);

        return response()->json([
            'data' => (new WarungResource($warung))->resolve($request),
        ]);
    }

    public function update(WarungUpdateRequest $request, string $id): JsonResponse
    {
        $warung = Warung::query()->findOrFail($id);
        Gate::authorize('update', $warung);

        $warung->fill($request->validated());
        $warung->save();

        return response()->json([
            'data' => (new WarungResource($warung))->resolve($request),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function activeFilter(array $filters): ?bool
    {
        if (! array_key_exists('aktif', $filters) || $filters['aktif'] === null) {
            return null;
        }

        return filter_var($filters['aktif'], FILTER_VALIDATE_BOOLEAN);
    }
}
