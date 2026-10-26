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
    public function startCheckout(string $invoiceNumber, string $idempotencyKey): array
    {
        return $this->data($this->send()->withHeader('Idempotency-Key', $idempotencyKey)
            ->post($this->invoicePath($invoiceNumber).'/checkout'));
    }

    /** @return array<string, mixed> */
    public function invoice(string $invoiceNumber): array
    {
        return $this->data($this->send()->get($this->invoicePath($invoiceNumber)));
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
