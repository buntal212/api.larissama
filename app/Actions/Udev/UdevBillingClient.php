<?php

namespace App\Actions\Udev;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class UdevBillingClient
{
    /** @return array<string, mixed> */
    public function validateReferral(string $referralCode): array
    {
        return $this->data($this->send()->post('/api/v1/integrations/referrals/validate', [
            'referral_code' => $referralCode,
        ]));
    }

    /** @return array<string, mixed> */
    public function attachReferral(string $externalCustomerId, string $referralCode, string $idempotencyKey): array
    {
        return $this->data($this->send()->withHeader('Idempotency-Key', $idempotencyKey)
            ->post($this->customerPath($externalCustomerId).'/referral', [
                'referral_code' => $referralCode,
            ]));
    }

    /** @return array<string, mixed> */
    public function preview(string $externalCustomerId, string $planCode, int $periodCount = 1): array
    {
        return $this->data($this->send()->post($this->customerPath($externalCustomerId).'/invoice-preview', [
            'subscription_plan_code' => $planCode,
            'period_count' => $periodCount,
        ]));
    }

    /** @return array<string, mixed> */
    public function createInvoice(
        string $externalCustomerId,
        string $planCode,
        int $periodCount,
        CarbonImmutable $dueAt,
        string $idempotencyKey,
    ): array {
        return $this->data($this->send()->withHeader('Idempotency-Key', $idempotencyKey)
            ->post($this->customerPath($externalCustomerId).'/invoices', [
                'subscription_plan_code' => $planCode,
                'period_count' => $periodCount,
                'due_at' => $dueAt->toIso8601String(),
            ]));
    }

    /** @return array<string, mixed> */
    public function startCheckout(
        string $invoiceNumber,
        string $idempotencyKey,
        string $paymentMethod = 'qris',
        string $provider = 'dana',
    ): array {
        $payload = [
            'payment_method' => $paymentMethod,
            'provider' => $provider,
        ];

        if (str_starts_with($paymentMethod, 'va_')) {
            $payload['bank'] = substr($paymentMethod, 3);
        }

        return $this->data($this->send()->withHeader('Idempotency-Key', $idempotencyKey)
            ->post($this->invoicePath($invoiceNumber).'/checkout', $payload));
    }

    public const array DanaMethodDefinitions = [
        'qris' => [
            'name' => 'QRIS (DANA)',
            'category' => 'qris',
            'provider' => 'dana',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 0.0,
            'fee_label' => 'Rp 0',
            'description' => 'DANA, GoPay, OVO, ShopeePay, BCA, Mandiri & Mobile Banking',
            'verified' => true,
            'is_primary' => true,
        ],
    ];

    /**
     * Definisi katalog metode gateway DANA pada UDEV dengan status verifikasi.
     * Hanya metode yang terverifikasi (verified = true) yang akan ditampilkan kepada pelanggan.
     * Metode VA / e-wallet yang belum terverifikasi disaring secara dinamis.
     *
     * @return array<string, array<string, mixed>>
     */
    public function danaGatewayMethods(): array
    {
        return [
            'qris' => [
                'code' => 'qris',
                'name' => 'QRIS (DANA)',
                'category' => 'qris',
                'provider' => 'dana',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'DANA, GoPay, OVO, ShopeePay, BCA, Mandiri & Mobile Banking',
                'verified' => true,
                'is_primary' => true,
            ],
            'dana_wallet' => [
                'code' => 'dana_wallet',
                'name' => 'DANA Wallet',
                'category' => 'ewallet',
                'provider' => 'dana',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'Bayar langsung melalui aplikasi DANA atau web checkout',
                'verified' => true,
                'is_primary' => false,
            ],
            'va_bri' => [
                'code' => 'va_bri',
                'name' => 'Virtual Account BRI (DANA)',
                'category' => 'virtual_account',
                'provider' => 'dana',
                'bank' => 'bri',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'Virtual Account Bank BRI via DANA',
                'verified' => true,
                'is_primary' => false,
            ],
            'va_cimb' => [
                'code' => 'va_cimb',
                'name' => 'Virtual Account CIMB Niaga (DANA)',
                'category' => 'virtual_account',
                'provider' => 'dana',
                'bank' => 'cimb',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'Virtual Account Bank CIMB Niaga via DANA',
                'verified' => true,
                'is_primary' => false,
            ],
            'va_btpn' => [
                'code' => 'va_btpn',
                'name' => 'Virtual Account BTPN / Jenius (DANA)',
                'category' => 'virtual_account',
                'provider' => 'dana',
                'bank' => 'btpn',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'Virtual Account Bank BTPN / Jenius via DANA',
                'verified' => true,
                'is_primary' => false,
            ],
            'va_pani' => [
                'code' => 'va_pani',
                'name' => 'Virtual Account Panin Bank (DANA)',
                'category' => 'virtual_account',
                'provider' => 'dana',
                'bank' => 'pani',
                'fee_type' => 'fixed',
                'fee_rate' => 0.0,
                'fixed_amount' => 0.0,
                'fee_label' => 'Rp 0',
                'description' => 'Virtual Account Bank Panin via DANA',
                'verified' => true,
                'is_primary' => false,
            ],
        ];
    }

    public const array MidtransMethodDefinitions = [
        'qris' => [
            'name' => 'QRIS',
            'category' => 'qris',
            'provider' => 'midtrans',
            'fee_type' => 'percent',
            'fee_rate' => 0.7,
            'fixed_amount' => 0.0,
            'fee_label' => '0,7%',
            'description' => 'BCA, Mandiri, GoPay, OVO, ShopeePay, DANA, LinkAja',
        ],
        'va_bca' => [
            'name' => 'Virtual Account BCA',
            'category' => 'virtual_account',
            'provider' => 'midtrans',
            'bank' => 'bca',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 4000.0,
            'fee_label' => 'Rp 4.000',
        ],
        'va_bni' => [
            'name' => 'Virtual Account BNI',
            'category' => 'virtual_account',
            'provider' => 'midtrans',
            'bank' => 'bni',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 4000.0,
            'fee_label' => 'Rp 4.000',
        ],
        'va_bri' => [
            'name' => 'Virtual Account BRI',
            'category' => 'virtual_account',
            'provider' => 'midtrans',
            'bank' => 'bri',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 4000.0,
            'fee_label' => 'Rp 4.000',
        ],
        'va_mandiri' => [
            'name' => 'Mandiri Bill Payment',
            'category' => 'virtual_account',
            'provider' => 'midtrans',
            'bank' => 'mandiri',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 4000.0,
            'fee_label' => 'Rp 4.000',
        ],
        'va_permata' => [
            'name' => 'Virtual Account Permata',
            'category' => 'virtual_account',
            'provider' => 'midtrans',
            'bank' => 'permata',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 4000.0,
            'fee_label' => 'Rp 4.000',
        ],
        'gopay' => [
            'name' => 'GoPay',
            'category' => 'ewallet',
            'provider' => 'midtrans',
            'fee_type' => 'percent',
            'fee_rate' => 2.0,
            'fixed_amount' => 0.0,
            'fee_label' => '2%',
        ],
    ];

    /** @return list<array<string, mixed>> */
    public function paymentMethods(): array
    {
        $all = $this->danaGatewayMethods();
        $verified = array_filter($all, fn (array $m): bool => ($m['verified'] ?? false) === true);

        // QRIS DANA selalu diutamakan sebagai metode utama
        usort($verified, function (array $a, array $b): int {
            if ($a['code'] === 'qris') {
                return -1;
            }
            if ($b['code'] === 'qris') {
                return 1;
            }

            return 0;
        });

        $methods = [];
        foreach ($verified as $code => $def) {
            $item = [
                'code' => (string) ($def['code'] ?? $code),
                'name' => $def['name'],
                'category' => $def['category'],
                'provider' => $def['provider'],
                'fee_type' => $def['fee_type'],
                'fee_rate' => $def['fee_rate'],
                'fixed_amount' => $def['fixed_amount'],
                'fee_label' => $def['fee_label'],
                'verified' => true,
                'is_primary' => (bool) ($def['is_primary'] ?? false),
            ];
            if (isset($def['bank'])) {
                $item['bank'] = $def['bank'];
            }
            if (isset($def['description'])) {
                $item['description'] = $def['description'];
            }
            $methods[] = $item;
        }

        return $methods;
    }

    /**
     * Hitung estimasi biaya admin dan total pembayaran berdasarkan subtotal invoice.
     *
     * @return array{
     *     subtotal: float,
     *     payment_method: string,
     *     payment_method_label: string,
     *     fee_type: string,
     *     fee_rate: float,
     *     fixed_amount: float,
     *     fee_amount: float,
     *     fee_label: string,
     *     total_amount: float,
     *     currency: string
     * }
     */
    public function calculateFee(float|int|string $subtotal, string $paymentMethod): array
    {
        $subtotalFloat = max(0.0, (float) $subtotal);
        $all = $this->danaGatewayMethods();
        $def = $all[$paymentMethod] ?? self::DanaMethodDefinitions[$paymentMethod] ?? [
            'name' => 'QRIS (DANA)',
            'fee_type' => 'fixed',
            'fee_rate' => 0.0,
            'fixed_amount' => 0.0,
            'fee_label' => 'Rp 0',
        ];

        if ($def['fee_type'] === 'percent') {
            $feeAmount = (float) round($subtotalFloat * ($def['fee_rate'] / 100));
            $feeLabel = str_replace('.', ',', (string) $def['fee_rate']).'%';
        } else {
            $feeAmount = (float) round((float) $def['fixed_amount']);
            $feeLabel = $def['fee_label'] ?? ('Rp '.number_format($def['fixed_amount'], 0, ',', '.').' (Tetap)');
        }

        $totalAmount = (float) round($subtotalFloat + $feeAmount);

        return [
            'subtotal' => $subtotalFloat,
            'payment_method' => $paymentMethod,
            'payment_method_label' => $def['name'],
            'fee_type' => $def['fee_type'],
            'fee_rate' => (float) $def['fee_rate'],
            'fixed_amount' => (float) $def['fixed_amount'],
            'fee_amount' => $feeAmount,
            'fee_label' => $feeLabel,
            'total_amount' => $totalAmount,
            'currency' => 'IDR',
        ];
    }

    /** @return array<string, mixed> */
    public function invoice(string $invoiceNumber, bool $reconcile = false): array
    {
        $query = $reconcile ? ['reconcile' => 1] : [];

        return $this->data($this->send()->get($this->invoicePath($invoiceNumber), $query));
    }

    /** @return list<array<string, mixed>> */
    public function transactions(string $externalCustomerId): array
    {
        $transactions = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->send()->get($this->customerPath($externalCustomerId).'/transactions', [
                'page' => $page,
                'per_page' => 100,
            ]);
            $data = $this->data($response);
            if (! array_is_list($data)) {
                throw new UnexpectedValueException('UDEV billing transactions response is invalid.');
            }

            foreach ($data as $transaction) {
                if (! is_array($transaction) || ! is_string($transaction['invoice_number'] ?? null)) {
                    throw new UnexpectedValueException('UDEV billing transactions response is invalid.');
                }
            }

            $transactions = [...$transactions, ...$data];
            $pagination = $response->json('meta');
            if ($pagination !== null && (! is_array($pagination)
                || ! is_int($pagination['last_page'] ?? null)
                || $pagination['last_page'] < $page)) {
                throw new UnexpectedValueException('UDEV billing transactions pagination is invalid.');
            }
            $lastPage = is_array($pagination) ? $pagination['last_page'] : 1;
            $page++;
        } while ($page <= $lastPage);

        return $transactions;
    }

    /** @return array<string, mixed> */
    public function subscription(string $externalCustomerId): array
    {
        return $this->data($this->send()->get($this->customerPath($externalCustomerId).'/subscription'));
    }

    private function send(): PendingRequest
    {
        $baseUrl = config('services.udev.base_url');
        $token = config('services.udev.token');
        $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || ! in_array($parts['path'] ?? '/', ['', '/'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! is_string($token)
            || $token === '') {
            throw new UdevBillingRequestException;
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout(10);
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function data(Response $response): array
    {
        if (! $response->successful()) {
            $requestId = $response->json('request_id');

            throw new UdevBillingRequestException(
                $response->status(),
                $response->header('Retry-After'),
                is_string($requestId) ? $requestId : null,
            );
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new UnexpectedValueException('UDEV billing response is invalid.');
        }

        return $data;
    }

    private function customerPath(string $externalCustomerId): string
    {
        return '/api/v1/integrations/customers/'.rawurlencode($externalCustomerId);
    }

    private function invoicePath(string $invoiceNumber): string
    {
        return '/api/v1/integrations/invoices/'.rawurlencode($invoiceNumber);
    }
}
