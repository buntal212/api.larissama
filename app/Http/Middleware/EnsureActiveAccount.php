<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof User) || ! ($user->currentAccessToken() instanceof PersonalAccessToken)) {
            return ApiErrorResponse::make(
                'UNAUTHENTICATED',
                'Login diperlukan atau kredensial/token tidak valid.',
                401,
            );
        }

        $user->loadMissing('warung');

        if (! $user->allowsApplicationAccessAt(CarbonImmutable::now('UTC'))) {
            return ApiErrorResponse::make(
                'FORBIDDEN',
                'Akun tidak memiliki akses aktif ke aplikasi.',
                403,
            );
        }

        return $next($request);
    }
}
