<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserIndexRequest;
use App\Http\Requests\Api\V1\UserStoreRequest;
use App\Http\Requests\Api\V1\UserUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiPaginationResponse;
use App\Models\User;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validated();
        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $query = $this->tenantUsers($actor);
        $search = $filters['q'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%');
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

        return ApiPaginationResponse::make($paginator, UserResource::class, $request, $page);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $attributes = $request->validated();
        $attributes['aktif'] ??= true;
        $user = $actor->warung()->firstOrFail()->users()->create($attributes);

        return response()->json([
            'data' => (new UserResource($user))->resolve($request),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $user = $this->tenantUsers($actor)->findOrFail($id);
        Gate::authorize('view', $user);

        return response()->json([
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function update(UserUpdateRequest $request, string $id): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $user = $this->tenantUsers($actor)->findOrFail($id);
        Gate::authorize('update', $user);

        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }

    private function tenantUsers(User $actor): Builder
    {
        return User::query()->where('warung_id', $actor->warung_id);
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
