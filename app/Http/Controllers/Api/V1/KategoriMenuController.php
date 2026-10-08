<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\KategoriMenuIndexRequest;
use App\Http\Requests\Api\V1\KategoriMenuStoreRequest;
use App\Http\Requests\Api\V1\KategoriMenuUpdateRequest;
use App\Http\Requests\Api\V1\TenantReadRequest;
use App\Http\Resources\Api\V1\KategoriMenuResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\KategoriMenu;
use App\Models\User;
use App\Models\Warung;
use App\Support\ApiPagination;
use App\Support\TenantReadScope;
use App\Support\TenantWriteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class KategoriMenuController extends Controller
{
    public function index(KategoriMenuIndexRequest $request, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', KategoriMenu::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $warungId = $tenantReadScope->resolve($actor, $filters);
        $query = $this->tenantCategories($warungId);

        if ($actor->role === 'kasir') {
            $query->where('aktif', true);
        } else {
            $active = $this->activeFilter($filters);

            if ($active !== null) {
                $query->where('aktif', $active);
            }
        }

        $search = $filters['q'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where('nama', 'like', '%'.$search.'%');
        }

        $sort = $filters['sort'] ?? 'urutan';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $page = $filters['page'] ?? '1';
        $paginator = ApiPagination::paginate(
            $query->orderBy($column, $direction)->orderBy('id', $direction),
            (int) ($filters['per_page'] ?? 20),
            $page,
        );

        return ApiPaginationResponse::make($paginator, KategoriMenuResource::class, $request, $page);
    }

    public function store(KategoriMenuStoreRequest $request, TenantWriteScope $writeScope): JsonResponse
    {
        Gate::authorize('create', KategoriMenu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $attributes = $request->validated();
        $warungId = $writeScope->resolve($actor, $attributes);
        unset($attributes['warung_id']);
        $attributes['urutan'] ??= 0;
        $attributes['aktif'] ??= true;
        $category = Warung::query()->findOrFail($warungId)->kategoriMenus()->create($attributes);

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)], 201);
    }

    public function show(TenantReadRequest $request, string $id, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', KategoriMenu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $warungId = $tenantReadScope->resolve($actor, $request->validated());
        $query = $this->tenantCategories($warungId);

        if ($actor->role === 'kasir') {
            $query->where('aktif', true);
        }

        $category = $query->findOrFail($id);
        Gate::authorize('view', [$category, $warungId]);

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)]);
    }

    public function update(KategoriMenuUpdateRequest $request, TenantWriteScope $writeScope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = $request->validated();
        $warungId = $writeScope->resolve($actor, $attributes);
        unset($attributes['warung_id']);
        $category = $this->tenantCategories((string) $warungId)->findOrFail($request->route('id'));
        Gate::authorize('update', [$category, (string) $warungId]);
        $category->fill($attributes);
        $category->save();

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)]);
    }

    private function tenantCategories(string $warungId): Builder
    {
        return KategoriMenu::query()->where('warung_id', $warungId);
    }

    /** @param array<string, mixed> $filters */
    private function activeFilter(array $filters): ?bool
    {
        if (! array_key_exists('aktif', $filters) || $filters['aktif'] === null) {
            return null;
        }

        return filter_var($filters['aktif'], FILTER_VALIDATE_BOOLEAN);
    }
}
