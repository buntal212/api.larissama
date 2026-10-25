<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevBillingRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachSubscriptionReferralRequest;
use App\Http\Requests\StartSubscriptionCheckoutRequest;
use App\Http\Requests\StoreSubscriptionInvoiceRequest;
use App\Http\Requests\SubscriptionInvoicePreviewRequest;
use App\Http\Requests\ValidateSubscriptionReferralRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use App\Models\Warung;
use App\Models\WarungCustomerSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class SubscriptionBillingController extends Controller
{
    public function validateReferral(ValidateSubscriptionReferralRequest $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        try {
            return response()->json(['data' => $udev->validateReferral($request->string('referral_code')->toString())]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function attachReferral(AttachSubscriptionReferralRequest $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        $idempotencyKey = $request->idempotencyKey();
        if ($idempotencyKey === null) {
            return ApiErrorResponse::make('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi dengan format yang valid.', 422);
        }

        try {
            return response()->json(['data' => $udev->attachReferral(
                $sync->external_customer_id,
                $request->string('referral_code')->toString(),
                $idempotencyKey,
            )]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function preview(SubscriptionInvoicePreviewRequest $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        try {
            return response()->json(['data' => $udev->preview($sync->external_customer_id, $request->string('subscription_plan_code')->toString())]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function store(StoreSubscriptionInvoiceRequest $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        $idempotencyKey = $request->idempotencyKey();
        if ($idempotencyKey === null) {
            return ApiErrorResponse::make('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi dengan format yang valid.', 422);
        }

        $dueAt = CarbonImmutable::now('Asia/Jakarta')->addDay();
        try {
            $invoice = $udev->createInvoice(
                $sync->external_customer_id,
                $request->string('subscription_plan_code')->toString(),
                $dueAt,
                $idempotencyKey,
            );

            return response()->json(['data' => $invoice], 201);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function checkout(StartSubscriptionCheckoutRequest $request, string $invoiceNumber, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        $idempotencyKey = $request->idempotencyKey();
        if ($idempotencyKey === null) {
            return ApiErrorResponse::make('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi dengan format yang valid.', 422);
        }

        try {
            if (! $this->invoiceBelongsToCustomer($udev, $sync, $invoiceNumber)) {
                return ApiErrorResponse::make('NOT_FOUND', 'Invoice tidak ditemukan.', 404);
            }

            $checkout = $udev->startCheckout($invoiceNumber, $idempotencyKey);

            return response()->json(['data' => $checkout], ($checkout['payment']['checkout_status'] ?? null) === 'ready' ? 200 : 202);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function show(Request $request, string $invoiceNumber, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        try {
            if (! $this->invoiceBelongsToCustomer($udev, $sync, $invoiceNumber)) {
                return ApiErrorResponse::make('NOT_FOUND', 'Invoice tidak ditemukan.', 404);
            }

            return response()->json(['data' => $udev->invoice($invoiceNumber)]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function transactions(Request $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        try {
            return response()->json(['data' => $udev->transactions($sync->external_customer_id)]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function reconcile(Request $request, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        try {
            $data = $udev->subscription($sync->external_customer_id);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }

        $subscription = $data['subscription'] ?? null;
        if (! is_array($subscription)
            || ! is_string($subscription['status'] ?? null)
            || ! is_string($subscription['starts_at'] ?? null)
            || ! is_string($subscription['expires_at'] ?? null)) {
            return ApiErrorResponse::make('UDEV_SUBSCRIPTION_NOT_ACTIVE', 'UDEV belum memiliki masa aktif hasil billing yang dapat diterapkan.', 409);
        }

        $warung = $request->user()->warung;
        if (! $warung instanceof Warung
            || ! is_string($warung->timezone)
            || ! in_array($warung->timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            return ApiErrorResponse::make('WARUNG_TIMEZONE_INVALID', 'Zona waktu warung belum valid.', 409);
        }

        $startsAt = CarbonImmutable::parse($subscription['starts_at'])->setTimezone($warung->timezone)->toDateString();
        $udevExpiresAt = CarbonImmutable::parse($subscription['expires_at'])->setTimezone($warung->timezone)->toDateString();
        $updatedWarung = DB::transaction(function () use ($warung, $startsAt, $udevExpiresAt): Warung {
            $lockedWarung = Warung::query()->lockForUpdate()->findOrFail($warung->getKey());
            $currentEnd = $lockedWarung->tanggal_berakhir?->toDateString();
            $effectiveEnd = $currentEnd !== null && $currentEnd > $udevExpiresAt ? $currentEnd : $udevExpiresAt;
            $lockedWarung->update([
                'tanggal_mulai' => $startsAt,
                'tanggal_berakhir' => $effectiveEnd,
            ]);

            $lockedWarung->refresh();

            return $lockedWarung;
        });

        return response()->json(['data' => [
            'subscription' => $subscription,
            'warung' => [
                'tanggal_mulai' => $updatedWarung->tanggal_mulai?->toDateString(),
                'tanggal_berakhir' => $updatedWarung->tanggal_berakhir?->toDateString(),
            ],
        ]]);
    }

    private function customerSync(Request $request): WarungCustomerSync|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || $actor->role !== 'owner' || $actor->warung_id === null) {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya owner warung yang dapat mengakses pembayaran langganan.', 403);
        }

        $sync = WarungCustomerSync::query()->where('warung_id', $actor->warung_id)->first();
        if (! $sync instanceof WarungCustomerSync || $sync->status !== 'synced' || $sync->consented_at === null) {
            return ApiErrorResponse::make('UDEV_CUSTOMER_NOT_READY', 'Data pelanggan warung belum tersinkron ke UDEV. Hubungi admin untuk bantuan.', 409);
        }

        return $sync;
    }

    private function invoiceBelongsToCustomer(UdevBillingClient $udev, WarungCustomerSync $sync, string $invoiceNumber): bool
    {
        foreach ($udev->transactions($sync->external_customer_id) as $transaction) {
            if (($transaction['invoice_number'] ?? null) === $invoiceNumber) {
                return true;
            }
        }

        return false;
    }

    private function upstreamFailure(Throwable $exception): JsonResponse
    {
        if ($exception instanceof UdevBillingRequestException) {
            $upstreamStatus = $exception->upstreamStatus;
            Log::warning('UDEV billing request failed', [
                'upstream_status' => $upstreamStatus,
                'upstream_request_id' => $exception->upstreamRequestId,
            ]);

            $status = match (true) {
                $upstreamStatus === 404 => 404,
                $upstreamStatus === 422 => 422,
                $upstreamStatus === 429, $upstreamStatus === null, $upstreamStatus >= 500 => 503,
                default => 502,
            };
            $response = ApiErrorResponse::make(
                $upstreamStatus === 404 ? 'NOT_FOUND' : ($status === 422 ? 'UDEV_BILLING_REJECTED' : 'UDEV_BILLING_UNAVAILABLE'),
                $status === 422
                    ? 'UDEV menolak permintaan billing. Periksa pilihan paket atau hubungi admin.'
                    : ($status === 404 ? 'Data billing tidak ditemukan di UDEV.' : 'Layanan billing UDEV sedang tidak dapat diakses.'),
                $status,
            );

            if ($upstreamStatus === 429 && is_string($exception->retryAfter)
                && preg_match('/^[0-9]{1,4}$/D', $exception->retryAfter) === 1) {
                $response->headers->set('Retry-After', $exception->retryAfter);
            }

            return $response;
        }

        Log::warning('UDEV billing response could not be processed', [
            'exception' => $exception::class,
        ]);

        return ApiErrorResponse::make(
            'UDEV_BILLING_UNAVAILABLE',
            'Respons billing UDEV tidak dapat diproses.',
            $exception instanceof UnexpectedValueException ? 502 : 503,
        );
    }
}
