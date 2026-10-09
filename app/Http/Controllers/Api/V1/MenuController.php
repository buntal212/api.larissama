<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MenuIndexRequest;
use App\Http\Requests\Api\V1\MenuStoreRequest;
use App\Http\Requests\Api\V1\MenuUpdateRequest;
use App\Http\Requests\Api\V1\TenantReadRequest;
use App\Http\Resources\Api\V1\MenuResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use App\Support\ApiPagination;
use App\Support\TenantReadScope;
use App\Support\TenantWriteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index(MenuIndexRequest $request, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $filters = $request->validated();
        $warungId = $tenantReadScope->resolve($actor, $filters);
        $query = $this->tenantMenus($warungId);

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

    public function store(MenuStoreRequest $request, TenantWriteScope $writeScope): JsonResponse
    {
        Gate::authorize('create', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $attributes = $request->validated();
        $warungId = $writeScope->resolve($actor, $attributes);
        unset($attributes['warung_id']);
        $attributes['kode'] = 'MNL-'.Str::ulid();
        $attributes['aktif'] ??= true;
        $menu = Warung::query()->findOrFail($warungId)->menus()->create($attributes);

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)], 201);
    }

    public function show(TenantReadRequest $request, string $id, TenantReadScope $tenantReadScope): JsonResponse
    {
        Gate::authorize('viewAny', Menu::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $warungId = $tenantReadScope->resolve($actor, $request->validated());
        $query = $this->tenantMenus($warungId);

        if ($actor->role === 'kasir') {
            $query->where('menus.aktif', true)
                ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('aktif', true));
        }

        $menu = $query->findOrFail($id);
        Gate::authorize('view', [$menu, $warungId]);

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)]);
    }

    public function update(MenuUpdateRequest $request, TenantWriteScope $writeScope): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = $request->validated();
        $warungId = $writeScope->resolve($actor, $attributes);
        unset($attributes['warung_id']);
        $menu = $this->tenantMenus((string) $warungId)->findOrFail($request->route('id'));
        Gate::authorize('update', $menu);
        $menu->fill($attributes);
        $menu->save();

        return response()->json(['data' => (new MenuResource($menu))->resolve($request)]);
    }

    private function tenantMenus(string $warungId): Builder
    {
        return Menu::query()
            ->where('warung_id', $warungId)
            ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('warung_id', $warungId));
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
