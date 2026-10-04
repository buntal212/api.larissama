<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\KategoriMenuIndexRequest;
use App\Http\Requests\Api\V1\KategoriMenuStoreRequest;
use App\Http\Requests\Api\V1\KategoriMenuUpdateRequest;
use App\Http\Resources\Api\V1\KategoriMenuResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\KategoriMenu;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KategoriMenuController extends Controller
{
    public function index(KategoriMenuIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', KategoriMenu::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $query = $this->tenantCategories($actor);

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
        $paginator = $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));

        return ApiPaginationResponse::make($paginator, KategoriMenuResource::class, $request);
    }

    public function store(KategoriMenuStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', KategoriMenu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $attributes = $request->validated();
        $attributes['urutan'] ??= 0;
        $attributes['aktif'] ??= true;
        $category = $actor->warung()->firstOrFail()->kategoriMenus()->create($attributes);

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', KategoriMenu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $query = $this->tenantCategories($actor);

        if ($actor->role === 'kasir') {
            $query->where('aktif', true);
        }

        $category = $query->findOrFail($id);
        Gate::authorize('view', $category);

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)]);
    }

    public function update(KategoriMenuUpdateRequest $request): JsonResponse
    {
        $category = $request->targetCategory();
        Gate::authorize('update', $category);
        $category->fill($request->validated());
        $category->save();

        return response()->json(['data' => (new KategoriMenuResource($category))->resolve($request)]);
    }

    private function tenantCategories(User $actor): Builder
    {
        return KategoriMenu::query()->where('warung_id', $actor->warung_id);
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
