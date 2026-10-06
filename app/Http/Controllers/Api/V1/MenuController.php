<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MenuIndexRequest;
use App\Http\Requests\Api\V1\MenuStoreRequest;
use App\Http\Requests\Api\V1\MenuUpdateRequest;
use App\Http\Resources\Api\V1\MenuResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Menu;
use App\Models\User;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index(MenuIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $query = $this->tenantMenus($actor);

        if ($actor->role === 'kasir') {
            $query->where('menus.aktif', true)
                ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('aktif', true));
        } else {
            $active = $this->activeFilter($filters);

            if ($active !== null) {
                $query->where('menus.aktif', $active);
            }
        }

        if (isset($filters['kategori_menu_id'])) {
            $query->where('kategori_menu_id', $filters['kategori_menu_id']);
        }

        $search = $filters['q'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('kode', 'like', '%'.$search.'%');
            });
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

        return ApiPaginationResponse::make($paginator, MenuResource::class, $request, $page);
    }

    public function store(MenuStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $attributes = $request->validated();
        $attributes['kode'] = 'MNL-'.Str::ulid();
        $attributes['aktif'] ??= true;
        $menu = $actor->warung()->firstOrFail()->menus()->create($attributes);

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $query = $this->tenantMenus($actor);

        if ($actor->role === 'kasir') {
            $query->where('menus.aktif', true)
                ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('aktif', true));
        }

        $menu = $query->findOrFail($id);
        Gate::authorize('view', $menu);

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)]);
    }

    public function update(MenuUpdateRequest $request): JsonResponse
    {
        $menu = $request->targetMenu();
        Gate::authorize('update', $menu);
        $menu->fill($request->validated());
        $menu->save();

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)]);
    }

    private function tenantMenus(User $actor): Builder
    {
        return Menu::query()
            ->where('warung_id', $actor->warung_id)
            ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('warung_id', $actor->warung_id));
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
