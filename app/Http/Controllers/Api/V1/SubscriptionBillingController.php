<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Udev\SubscriptionInvoiceRegistry;
use App\Actions\Udev\SubscriptionReconcileService;
use App\Actions\Udev\UdevBillingClient;
use App\Actions\Udev\UdevBillingRequestException;
use App\Actions\Udev\UdevSyncLimiter;
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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class SubscriptionBillingController extends Controller
{
    public const int STATUS_CACHE_TTL_SECONDS = 15;
    public const int STALE_MAX_AGE_SECONDS = 300;
    public const int MAX_POLL_CHECKS = 30;
    public const int MAX_POLL_DURATION_SECONDS = 300;
    public const int CHECKOUT_LOCK_TTL_SECONDS = 60;
    public const int CHECKOUT_LOCK_WAIT_SECONDS = 15;

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
            $preview = $udev->preview(
                $sync->external_customer_id,
                $request->string('subscription_plan_code')->toString(),
                $request->integer('period_count', 1),
            );

            $this->validatePricingConsistency($preview['pricing'] ?? null, $request->integer('period_count', 1));

            return response()->json(['data' => $preview]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function store(StoreSubscriptionInvoiceRequest $request, UdevBillingClient $udev, SubscriptionInvoiceRegistry $registry): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        $idempotencyKey = $request->idempotencyKey();
        if ($idempotencyKey === null) {
            return ApiErrorResponse::make('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi dengan format yang valid.', 422);
        }

        $planCode = $request->string('subscription_plan_code')->toString();
        $periodCount = $request->integer('period_count', 1);

        try {
            return Cache::lock("udev_invoice_lock:{$sync->external_customer_id}", 15)->block(15, function () use ($udev, $sync, $planCode, $periodCount, $idempotencyKey, $registry): JsonResponse {
                // Gunakan kembali invoice pending yang cocok dan masih valid
                $transactions = $udev->transactions($sync->external_customer_id);

                foreach ($transactions as $tx) {
                    $txStatus = $tx['status'] ?? null;
                    $txPlanCode = $tx['plan']['code'] ?? null;
                    $txPeriodCount = (int) ($tx['period_count'] ?? $tx['plan']['period_count'] ?? 1);
                    $txDueAt = isset($tx['due_at']) ? CarbonImmutable::parse($tx['due_at']) : null;

                    if ($txStatus === 'pending'
                        && $txPlanCode === $planCode
                        && $txPeriodCount === $periodCount
                        && $txDueAt !== null
                        && $txDueAt->isFuture()
                    ) {
                        $this->validatePricingConsistency($tx['pricing'] ?? null, $periodCount);
                        $registry->register((string) $tx['invoice_number'], (int) $sync->warung_id, (string) $sync->external_customer_id, 'pending');

                        return response()->json(['data' => $tx], 200);
                    }
                }

                $dueAt = CarbonImmutable::now('Asia/Jakarta')->addDay();
                $invoice = $udev->createInvoice(
                    $sync->external_customer_id,
                    $planCode,
                    $periodCount,
                    $dueAt,
                    $idempotencyKey,
                );

                $this->validatePricingConsistency($invoice['pricing'] ?? null, $periodCount);
                $registry->register((string) $invoice['invoice_number'], (int) $sync->warung_id, (string) $sync->external_customer_id, 'pending');

                return response()->json(['data' => $invoice], 201);
            });
        } catch (LockTimeoutException) {
            $res = ApiErrorResponse::make('CHECKOUT_BUSY', 'Sistem sedang memproses invoice pelanggan ini. Silakan coba beberapa saat lagi.', 429);
            $res->headers->set('Retry-After', '5');

            return $res;
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function paymentMethods(Request $request, UdevBillingClient $udev): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! in_array($actor->role, ['owner', 'superadmin'], true)) {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya owner warung atau superadmin yang dapat mengakses metode pembayaran.', 403);
        }

        return response()->json(['data' => $udev->paymentMethods()]);
    }

    public function feePreview(Request $request, string $invoiceNumber, UdevBillingClient $udev): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }

        $paymentMethod = (string) ($request->input('payment_method') ?? 'qris');
        $validMethods = array_column($udev->paymentMethods(), 'code');
        if (! in_array($paymentMethod, $validMethods, true)) {
            return ApiErrorResponse::make('INVALID_PAYMENT_METHOD', 'Metode pembayaran tidak valid untuk DANA.', 422);
        }

        try {
            if (! $this->invoiceBelongsToCustomer($udev, $sync, $invoiceNumber)) {
                return ApiErrorResponse::make('NOT_FOUND', 'Invoice tidak ditemukan.', 404);
            }

            $invoice = $udev->invoice($invoiceNumber);
            $subtotal = (float) ($invoice['pricing']['final_price'] ?? $invoice['amount'] ?? 0);

            $activePayment = $invoice['payment'] ?? null;
            if (
                is_array($activePayment) &&
                ($activePayment['payment_method'] ?? null) === $paymentMethod &&
                is_array($activePayment['fee_breakdown'] ?? null)
            ) {
                $fb = $activePayment['fee_breakdown'];
                $feeType = (string) ($fb['fee_type'] ?? 'fixed');
                $feeRate = (float) ($fb['fee_rate'] ?? 0);
                $fixedAmount = (float) ($fb['fixed_amount'] ?? 0);
                $feeLabel = $feeType === 'percent'
                    ? str_replace('.', ',', (string) $feeRate).'%'
                    : 'Rp '.number_format($fixedAmount, 0, ',', '.').' (Tetap)';

                return response()->json([
                    'data' => [
                        'invoice_number' => $invoiceNumber,
                        'subtotal' => (float) ($fb['subtotal_amount'] ?? $subtotal),
                        'payment_method' => $paymentMethod,
                        'payment_method_label' => (string) ($fb['payment_method_label'] ?? 'QRIS (DANA)'),
                        'fee_type' => $feeType,
                        'fee_rate' => $feeRate,
                        'fixed_amount' => $fixedAmount,
                        'fee_amount' => (float) ($fb['fee_amount'] ?? 0),
                        'fee_label' => $feeLabel,
                        'total_amount' => (float) ($fb['total_amount'] ?? $activePayment['amount'] ?? ($subtotal + (float) ($fb['fee_amount'] ?? 0))),
                        'currency' => (string) ($fb['currency'] ?? 'IDR'),
                        'is_locked' => true,
                    ],
                ]);
            }

            $calc = $udev->calculateFee($subtotal, $paymentMethod);
            $calc['invoice_number'] = $invoiceNumber;
            $calc['is_locked'] = false;

            return response()->json(['data' => $calc]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function checkout(StartSubscriptionCheckoutRequest $request, string $invoiceNumber, UdevBillingClient $udev, SubscriptionInvoiceRegistry $registry): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }
        $idempotencyKey = $request->idempotencyKey();
        if ($idempotencyKey === null) {
            return ApiErrorResponse::make('IDEMPOTENCY_KEY_REQUIRED', 'Header Idempotency-Key wajib diisi dengan format yang valid.', 422);
        }

        $paymentMethod = $request->validated('payment_method') ?? 'qris';
        $provider = $request->validated('provider') ?? 'dana';

        try {
            return Cache::lock("udev_checkout_lock:{$invoiceNumber}", self::CHECKOUT_LOCK_TTL_SECONDS)->block(self::CHECKOUT_LOCK_WAIT_SECONDS, function () use ($udev, $sync, $invoiceNumber, $idempotencyKey, $paymentMethod, $provider, $registry): JsonResponse {
                if (! $this->invoiceBelongsToCustomer($udev, $sync, $invoiceNumber)) {
                    return ApiErrorResponse::make('NOT_FOUND', 'Invoice tidak ditemukan.', 404);
                }

                $invoice = $udev->invoice($invoiceNumber);
                $this->validatePricingConsistency($invoice['pricing'] ?? null, (int) ($invoice['period_count'] ?? 1));
                $registry->register($invoiceNumber, (int) $sync->warung_id, (string) $sync->external_customer_id, (string) ($invoice['status'] ?? 'pending'));

                if (($invoice['status'] ?? null) === 'paid') {
                    $this->storeInvoiceCache($invoiceNumber, $invoice);
                    $pollingSession = $this->resolvePollingSession($invoiceNumber, false);
                    $invoice['polling_session'] = $pollingSession;
                    $invoice['is_stale'] = false;

                    return response()->json(['data' => $invoice], 200);
                }

                $activePayment = $invoice['payment'] ?? null;
                if (is_array($activePayment)) {
                    $attemptStatus = $activePayment['status'] ?? null;
                    $expiresAt = isset($activePayment['checkout_expires_at']) ? CarbonImmutable::parse($activePayment['checkout_expires_at']) : null;
                    $isAttemptActive = in_array($attemptStatus, ['initiated', 'pending'], true)
                        && ($expiresAt === null || $expiresAt->isFuture());

                    if ($isAttemptActive) {
                        $existingMethod = $activePayment['payment_method'] ?? 'qris';
                        if ($paymentMethod !== $existingMethod) {
                            return ApiErrorResponse::make(
                                'PAYMENT_METHOD_LOCKED',
                                'Metode pembayaran tidak dapat diganti karena instruksi pembayaran sebelumnya masih aktif. Selesaikan pembayaran atau tunggu hingga kedaluwarsa.',
                                409
                            );
                        }

                        if (($activePayment['checkout_status'] ?? null) === 'ready') {
                            $this->storeInvoiceCache($invoiceNumber, $invoice);
                            $pollingSession = $this->resolvePollingSession($invoiceNumber, false);
                            $invoice['polling_session'] = $pollingSession;
                            $invoice['is_stale'] = false;

                            return response()->json(['data' => $invoice], 200);
                        }
                    }
                }

                try {
                    $checkout = $udev->startCheckout(
                        $invoiceNumber,
                        $idempotencyKey,
                        $paymentMethod,
                        $provider,
                    );

                    $this->storeInvoiceCache($invoiceNumber, $checkout);
                    $pollingSession = $this->resolvePollingSession($invoiceNumber, false);
                    $checkout['polling_session'] = $pollingSession;
                    $checkout['is_stale'] = false;

                    $isReady = ($checkout['payment']['checkout_status'] ?? null) === 'ready';

                    return response()->json(['data' => $checkout], $isReady ? 200 : 202);
                } catch (Throwable $exception) {
                    try {
                        $verifiedInvoice = $udev->invoice($invoiceNumber);
                        $this->storeInvoiceCache($invoiceNumber, $verifiedInvoice);
                        $pollingSession = $this->resolvePollingSession($invoiceNumber, false);
                        $verifiedInvoice['polling_session'] = $pollingSession;
                        $verifiedInvoice['is_stale'] = false;

                        $verifiedPayment = $verifiedInvoice['payment'] ?? null;
                        if (is_array($verifiedPayment) && in_array($verifiedPayment['status'] ?? null, ['initiated', 'pending'], true)) {
                            $isReady = ($verifiedPayment['checkout_status'] ?? null) === 'ready';

                            return response()->json(['data' => $verifiedInvoice], $isReady ? 200 : 202);
                        }
                        if (($verifiedInvoice['status'] ?? null) === 'paid') {
                            return response()->json(['data' => $verifiedInvoice], 200);
                        }
                    } catch (Throwable) {
                        // Verifikasi gagal, teruskan exception asli
                    }

                    throw $exception;
                }
            });
        } catch (LockTimeoutException) {
            try {
                $currentInvoice = $udev->invoice($invoiceNumber);
                $this->storeInvoiceCache($invoiceNumber, $currentInvoice);
                $pollingSession = $this->resolvePollingSession($invoiceNumber, false);
                $currentInvoice['polling_session'] = $pollingSession;
                $currentInvoice['is_stale'] = false;

                $res = response()->json([
                    'code' => 'CHECKOUT_BUSY',
                    'message' => 'Antrean checkout sedang sibuk. Silakan periksa status pembayaran atau coba beberapa saat lagi.',
                    'data' => $currentInvoice,
                ], 429);
                $res->headers->set('Retry-After', '5');

                return $res;
            } catch (Throwable) {
                $res = ApiErrorResponse::make('CHECKOUT_BUSY', 'Antrean checkout sedang sibuk. Silakan coba lagi beberapa saat.', 429);
                $res->headers->set('Retry-After', '5');

                return $res;
            }
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

            $reconcile = $request->boolean('reconcile');
            $isPolling = $request->boolean('polling');

            $statusResult = $this->resolveInvoiceStatus($invoiceNumber, $udev, $reconcile);
            $pollingSession = $this->resolvePollingSession($invoiceNumber, $isPolling);

            $invoiceData = $statusResult['data'];
            $invoiceData['polling_session'] = $pollingSession;
            $invoiceData['is_stale'] = $statusResult['is_stale'];

            return response()->json([
                'data' => $invoiceData,
                'meta' => [
                    'cached' => ! $reconcile && ($statusResult['cached_at'] !== null),
                    'stale' => $statusResult['is_stale'],
                    'cached_at' => $statusResult['cached_at'],
                    'polling_session' => $pollingSession,
                ],
            ]);
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

    public function reconcile(Request $request, UdevBillingClient $udev, SubscriptionReconcileService $reconcileService): JsonResponse
    {
        $sync = $this->customerSync($request);
        if ($sync instanceof JsonResponse) {
            return $sync;
        }

        try {
            $invoiceNumber = $request->query('invoice_number') ?? $request->input('invoice_number');
            $result = $reconcileService->reconcileWarung(
                (int) $sync->warung_id,
                $sync->external_customer_id,
                is_string($invoiceNumber) && $invoiceNumber !== '' ? $invoiceNumber : null,
            );

            if (! $result['success']) {
                return ApiErrorResponse::make(
                    $result['error_code'] ?? 'UDEV_SUBSCRIPTION_NOT_ACTIVE',
                    $result['error_message'] ?? 'UDEV belum memiliki masa aktif hasil billing yang dapat diterapkan.',
                    409,
                );
            }

            return response()->json(['data' => [
                'updated' => $result['updated'],
                'subscription' => $result['subscription'],
                'warung' => [
                    'tanggal_mulai' => $result['warung']['tanggal_mulai'] ?? null,
                    'tanggal_berakhir' => $result['warung']['tanggal_berakhir'] ?? null,
                ],
            ]]);
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception);
        }
    }

    public function monitoring(
        Request $request,
        SubscriptionInvoiceRegistry $registry,
        UdevSyncLimiter $limiter,
        SubscriptionReconcileService $reconcileService,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User || $actor->role !== 'superadmin') {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya superadmin yang dapat mengakses data monitoring.', 403);
        }

        return response()->json([
            'data' => [
                'registry' => $registry->getSummary(),
                'limiter' => $limiter->getState(),
                'recent_logs' => $reconcileService->getRecentLogs(20),
            ],
        ]);
    }

    public function manualResync(
        Request $request,
        UdevBillingClient $udev,
        SubscriptionReconcileService $reconcileService,
        SubscriptionInvoiceRegistry $registry,
        UdevSyncLimiter $limiter,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User || $actor->role !== 'superadmin') {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya superadmin yang dapat menjalankan manual re-sync.', 403);
        }

        $warungId = $request->input('warung_id') ?? $request->query('warung_id');
        if (! is_numeric($warungId) || (int) $warungId <= 0) {
            return ApiErrorResponse::make('WARUNG_REQUIRED', 'Parameter warung_id wajib diisi dengan benar.', 422);
        }

        $sync = WarungCustomerSync::query()->where('warung_id', (int) $warungId)->first();
        if (! $sync instanceof WarungCustomerSync || $sync->status !== 'synced' || $sync->consented_at === null) {
            return ApiErrorResponse::make('UDEV_CUSTOMER_NOT_READY', 'Data warung belum tersinkron ke UDEV.', 409);
        }

        if (! $limiter->canExecute('manual', true)) {
            $res = ApiErrorResponse::make('RATE_LIMITED', 'Batas kuota UDEV tercapai (maksimal 10 request/menit). Coba lagi nanti.', 429);
            $res->headers->set('Retry-After', '60');

            return $res;
        }

        $invoiceNumber = $request->input('invoice_number') ?? $request->query('invoice_number');
        $invoiceNumber = is_string($invoiceNumber) && $invoiceNumber !== '' ? $invoiceNumber : null;

        try {
            $limiter->recordRequest();
            if ($invoiceNumber !== null) {
                $liveInvoice = $udev->invoice($invoiceNumber, true);
                if (($liveInvoice['status'] ?? null) === 'paid') {
                    $registry->updateStatus($invoiceNumber, 'paid');
                }
            }

            $result = $reconcileService->reconcileWarung((int) $warungId, $sync->external_customer_id, $invoiceNumber);
            $limiter->recordSuccess();

            if (! $result['success']) {
                return ApiErrorResponse::make(
                    $result['error_code'] ?? 'UDEV_SUBSCRIPTION_NOT_ACTIVE',
                    $result['error_message'] ?? 'UDEV belum memiliki masa aktif subscription yang dapat diterapkan.',
                    409,
                );
            }

            return response()->json([
                'data' => [
                    'updated' => $result['updated'],
                    'subscription' => $result['subscription'],
                    'warung' => $result['warung'],
                ],
            ]);
        } catch (Throwable $exception) {
            $limiter->recordFailure($exception);

            return $this->upstreamFailure($exception);
        }
    }

    private function customerSync(Request $request): WarungCustomerSync|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! in_array($actor->role, ['owner', 'superadmin'], true)) {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya owner warung atau superadmin yang dapat mengakses pembayaran langganan.', 403);
        }

        if ($actor->role === 'owner' && $actor->warung_id === null) {
            return ApiErrorResponse::make('FORBIDDEN', 'Hanya owner warung yang dapat mengakses pembayaran langganan.', 403);
        }

        $warungId = $actor->role === 'superadmin'
            ? ($request->query('warung_id') ?? $request->input('warung_id'))
            : $actor->warung_id;

        if ($warungId === null || $warungId === '') {
            return ApiErrorResponse::make('WARUNG_REQUIRED', 'Pilih warung terlebih dahulu untuk mengakses data langganan.', 422);
        }

        $sync = WarungCustomerSync::query()->where('warung_id', $warungId)->first();
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

    public function storeInvoiceCache(string $invoiceNumber, array $invoiceData): void
    {
        $payload = [
            'data' => $invoiceData,
            'cached_at' => CarbonImmutable::now()->timestamp,
        ];
        Cache::put("invoice_fresh:{$invoiceNumber}", $payload, self::STATUS_CACHE_TTL_SECONDS);
        Cache::put("invoice_stale:{$invoiceNumber}", $payload, self::STALE_MAX_AGE_SECONDS);
    }

    /**
     * @return array{started_at: int, elapsed_seconds: int, remaining_seconds: int, check_count: int, max_checks: int, max_duration_seconds: int, is_active: bool}
     */
    public function resolvePollingSession(string $invoiceNumber, bool $isPolling = false): array
    {
        $cacheKey = "polling_session:{$invoiceNumber}";
        $now = CarbonImmutable::now()->timestamp;

        $session = Cache::get($cacheKey);
        if (! is_array($session) || ! isset($session['started_at'])) {
            $session = [
                'started_at' => $now,
                'check_count' => 0,
            ];
        }

        if ($isPolling) {
            $session['check_count'] = ((int) ($session['check_count'] ?? 0)) + 1;
        }

        Cache::put($cacheKey, $session, 600);

        $elapsedSeconds = max(0, $now - (int) $session['started_at']);
        $remainingSeconds = max(0, self::MAX_POLL_DURATION_SECONDS - $elapsedSeconds);
        $checksRemaining = max(0, self::MAX_POLL_CHECKS - (int) $session['check_count']);
        $isActive = $remainingSeconds > 0 && $checksRemaining > 0;

        return [
            'started_at' => (int) $session['started_at'],
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'check_count' => (int) $session['check_count'],
            'max_checks' => self::MAX_POLL_CHECKS,
            'max_duration_seconds' => self::MAX_POLL_DURATION_SECONDS,
            'is_active' => $isActive,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, is_stale: bool, cached_at: int|null}
     */
    public function resolveInvoiceStatus(string $invoiceNumber, UdevBillingClient $udev, bool $forceFresh = false): array
    {
        if ($forceFresh) {
            $liveData = $udev->invoice($invoiceNumber, true);
            $this->storeInvoiceCache($invoiceNumber, $liveData);

            return [
                'data' => $liveData,
                'is_stale' => false,
                'cached_at' => CarbonImmutable::now()->timestamp,
            ];
        }

        $fresh = Cache::get("invoice_fresh:{$invoiceNumber}");
        if (is_array($fresh) && isset($fresh['data'])) {
            return [
                'data' => $fresh['data'],
                'is_stale' => false,
                'cached_at' => $fresh['cached_at'] ?? null,
            ];
        }

        $stale = Cache::get("invoice_stale:{$invoiceNumber}");
        if (is_array($stale) && isset($stale['data'])) {
            $age = CarbonImmutable::now()->timestamp - ((int) ($stale['cached_at'] ?? 0));
            $lock = Cache::lock("invoice_revalidate_lock:{$invoiceNumber}", 5);
            if ($lock->get()) {
                try {
                    $liveData = $udev->invoice($invoiceNumber, false);
                    $this->storeInvoiceCache($invoiceNumber, $liveData);

                    return [
                        'data' => $liveData,
                        'is_stale' => false,
                        'cached_at' => CarbonImmutable::now()->timestamp,
                    ];
                } catch (Throwable $e) {
                    Log::warning("UDEV invoice revalidation failed for {$invoiceNumber}, serving stale cache", [
                        'exception' => $e->getMessage(),
                    ]);

                    if ($age > self::STALE_MAX_AGE_SECONDS) {
                        throw new UnexpectedValueException('STATUS_UNVERIFIED');
                    }

                    return [
                        'data' => $stale['data'],
                        'is_stale' => true,
                        'cached_at' => $stale['cached_at'] ?? null,
                    ];
                } finally {
                    $lock->release();
                }
            }

            // Another process is already revalidating; collapse request
            if ($age > self::STALE_MAX_AGE_SECONDS) {
                throw new UnexpectedValueException('STATUS_UNVERIFIED');
            }

            return [
                'data' => $stale['data'],
                'is_stale' => true,
                'cached_at' => $stale['cached_at'] ?? null,
            ];
        }

        // Cache missing; query UDEV directly
        $liveData = $udev->invoice($invoiceNumber, false);
        $this->storeInvoiceCache($invoiceNumber, $liveData);

        return [
            'data' => $liveData,
            'is_stale' => false,
            'cached_at' => CarbonImmutable::now()->timestamp,
        ];
    }

    /**
     * Validasi konsistensi rincian harga dari UDEV secara matematis.
     * Sumber kebenaran harga adalah UDEV, namun jika UDEV memberikan nilai
     * yang tidak konsisten secara matematis (misal: normal - diskon != total),
     * Larissama wajib memblokir pembuatan invoice demi integritas data billing.
     *
     * @param  array<string, mixed>|null  $pricing
     *
     * @throws UnexpectedValueException
     */
    public function validatePricingConsistency(?array $pricing, int $expectedPeriodCount = 1): void
    {
        if (! is_array($pricing)) {
            throw new UnexpectedValueException('UDEV_PRICING_INCONSISTENT: Data rincian harga tidak ditemukan.');
        }

        $normalPrice = isset($pricing['normal_price']) ? (float) $pricing['normal_price'] : -1.0;
        $discountAmount = isset($pricing['discount_amount']) ? (float) $pricing['discount_amount'] : 0.0;
        $finalPrice = isset($pricing['final_price']) ? (float) $pricing['final_price'] : -1.0;
        $unitPrice = isset($pricing['unit_price']) ? (float) $pricing['unit_price'] : null;
        $periodCount = isset($pricing['period_count']) ? (int) $pricing['period_count'] : $expectedPeriodCount;

        if ($normalPrice < 0 || $discountAmount < 0 || $finalPrice < 0 || $periodCount < 1) {
            throw new UnexpectedValueException('UDEV_PRICING_INCONSISTENT: Komponen harga tidak boleh bernilai negatif dan periode minimal 1.');
        }

        if ($discountAmount > $normalPrice) {
            throw new UnexpectedValueException('UDEV_PRICING_INCONSISTENT: Nilai diskon tidak boleh melebihi harga normal paket.');
        }

        if ($unitPrice !== null && $unitPrice >= 0) {
            $expectedNormal = round($unitPrice * $periodCount, 2);
            if (abs($expectedNormal - round($normalPrice, 2)) >= 0.01) {
                throw new UnexpectedValueException('UDEV_PRICING_INCONSISTENT: Harga normal tidak sesuai dengan perkalian harga per periode.');
            }
        }

        $expectedFinal = round($normalPrice - $discountAmount, 2);
        if (abs($expectedFinal - round($finalPrice, 2)) >= 0.01) {
            throw new UnexpectedValueException('UDEV_PRICING_INCONSISTENT: Total invoice tidak sesuai dengan harga normal dikurangi diskon.');
        }
    }

    private function upstreamFailure(Throwable $exception): JsonResponse
    {
        if ($exception instanceof UnexpectedValueException && str_starts_with($exception->getMessage(), 'UDEV_PRICING_INCONSISTENT')) {
            Log::warning('UDEV pricing inconsistency detected', [
                'error' => $exception->getMessage(),
            ]);

            return ApiErrorResponse::make(
                'UDEV_PRICING_INCONSISTENT',
                'Rincian harga dari UDEV tidak konsisten secara matematis. Pembuatan invoice diblokir.',
                422,
            );
        }

        if ($exception instanceof UnexpectedValueException && $exception->getMessage() === 'STATUS_UNVERIFIED') {
            return ApiErrorResponse::make(
                'STATUS_UNVERIFIED',
                'Status pembayaran terbaru belum dapat diverifikasi. Silakan coba beberapa saat lagi.',
                503,
            );
        }

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
