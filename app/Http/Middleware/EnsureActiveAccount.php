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

        $now = CarbonImmutable::now('UTC');
        $paymentGrace = $user->warung?->allowsSubscriptionPaymentAccessAt($now) ?? false;

        if (! $user->allowsApplicationAccessAt($now)) {
            $graceExpired = $user->warung?->isBeyondSubscriptionPaymentAccessAt($now) ?? false;

            return ApiErrorResponse::make(
                $graceExpired
                    ? 'SUBSCRIPTION_GRACE_EXPIRED'
                    : ($paymentGrace ? 'SUBSCRIPTION_PAYMENT_REQUIRED' : 'FORBIDDEN'),
                $graceExpired
                    ? 'Masa tenggang langganan telah berakhir. Hubungi admin untuk mengaktifkan kembali akun.'
                    : ($paymentGrace
                        ? 'Hanya pemilik warung yang dapat login selama masa tenggang langganan.'
                        : 'Akun tidak memiliki akses aktif ke aplikasi.'),
                403,
            );
        }

        if ($user->hasSubscriptionPaymentOnlyAccessAt($now)
            && ! in_array($request->route()?->getName(), [
                'api.v1.auth.me',
                'api.v1.auth.logout',
                'api.v1.subscription-plans.index',
            ], true)) {
            return ApiErrorResponse::make(
                'SUBSCRIPTION_PAYMENT_REQUIRED',
                'Masa aktif warung telah habis. Selama masa tenggang, hanya menu paket langganan yang dapat diakses.',
                403,
            );
        }

        return $next($request);
    }
}
