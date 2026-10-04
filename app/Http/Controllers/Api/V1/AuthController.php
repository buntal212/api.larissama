<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\WarungResource;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('username', $credentials['username'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return ApiErrorResponse::make(
                'UNAUTHENTICATED',
                'Username atau password tidak valid.',
                401,
            );
        }

        $user->loadMissing('warung');
        $issuedAt = CarbonImmutable::now('UTC');

        if (! $user->allowsApplicationAccessAt($issuedAt)) {
            return ApiErrorResponse::make(
                'FORBIDDEN',
                'Akun tidak memiliki akses aktif ke aplikasi.',
                403,
            );
        }

        $expiresAt = $issuedAt->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken('LarisSama API', ['*'], $expiresAt);

        return response()->json([
            'data' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
                'user' => (new UserResource($user))->resolve($request),
                'warung' => $user->warung === null
                    ? null
                    : (new WarungResource($user->warung))->resolve($request),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiErrorResponse::make(
                'UNAUTHENTICATED',
                'Login diperlukan atau kredensial/token tidak valid.',
                401,
            );
        }

        $user->loadMissing('warung');

        return response()->json([
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'warung' => $user->warung === null
                    ? null
                    : (new WarungResource($user->warung))->resolve($request),
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiErrorResponse::make(
                'UNAUTHENTICATED',
                'Login diperlukan atau kredensial/token tidak valid.',
                401,
            );
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return ApiErrorResponse::make(
                'UNAUTHENTICATED',
                'Login diperlukan atau kredensial/token tidak valid.',
                401,
            );
        }

        $token->delete();

        return response()->noContent();
    }
}
