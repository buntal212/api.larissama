<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Udev\UdevSubscriptionPlanClient;
use App\Actions\Udev\UdevSubscriptionPlanRequestException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request, UdevSubscriptionPlanClient $client): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || $actor->role !== 'owner' || $actor->warung_id === null) {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya owner warung yang dapat melihat paket langganan.', 403);
        }

        try {
            return response()->json(['data' => $client->list()]);
        } catch (UdevSubscriptionPlanRequestException $exception) {
            Log::warning('UDEV subscription plan catalog request failed', [
                'upstream_status' => $exception->upstreamStatus,
            ]);

            $status = in_array($exception->upstreamStatus, [null, 429], true) ? 503 : 502;
            $response = ApiErrorResponse::make(
                'UDEV_CATALOG_UNAVAILABLE',
                'Katalog paket langganan sedang tidak dapat diakses.',
                $status,
            );

            if ($exception->upstreamStatus === 429
                && is_string($exception->retryAfter)
                && preg_match('/^[0-9]{1,4}$/D', $exception->retryAfter) === 1) {
                $response->headers->set('Retry-After', $exception->retryAfter);
            }

            return $response;
        } catch (Throwable $exception) {
            Log::warning('UDEV subscription plan catalog request failed', [
                'exception' => $exception::class,
            ]);

            return ApiErrorResponse::make(
                'UDEV_CATALOG_UNAVAILABLE',
                'Katalog paket langganan sedang tidak dapat diakses.',
                $exception instanceof UnexpectedValueException ? 502 : 503,
            );
        }
    }
}
