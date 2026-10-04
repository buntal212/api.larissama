<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WarungResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CurrentWarungController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        Gate::authorize('viewCurrent', $user);

        $warung = $user->warung;
        abort_if($warung === null, 404);

        return response()->json([
            'data' => (new WarungResource($warung))->resolve($request),
        ]);
    }
}
