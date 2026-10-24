<?php

namespace App\Actions\Udev;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class UdevSubscriptionPlanClient
{
    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     price: string,
     *     currency: string,
     *     duration_days: int,
     *     is_active: bool
     * }>
     *
     * @throws ConnectionException
     * @throws UdevSubscriptionPlanRequestException
     * @throws UnexpectedValueException
     */
    public function list(): array
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
            throw new UdevSubscriptionPlanRequestException();
        }

        $request = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout(10);
        $plans = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $request->get('/api/v1/integrations/subscription-plans', [
                'page' => $page,
                'per_page' => 100,
            ]);

            if (! $response->successful()) {
                throw new UdevSubscriptionPlanRequestException(
                    $response->status(),
                    $response->header('Retry-After'),
                );
            }

            $pagePlans = $response->json('data');
            $pagination = $response->json('meta');
            if (! is_array($pagePlans)
                || ! array_is_list($pagePlans)
                || ($pagination !== null && (! is_array($pagination) || ! is_int($pagination['last_page'] ?? null) || $pagination['last_page'] < $page))) {
                throw new UnexpectedValueException('UDEV subscription plan response is invalid.');
            }

            $plans = [...$plans, ...$pagePlans];
            $lastPage = is_array($pagination) ? $pagination['last_page'] : 1;
            $page++;
        } while ($page <= $lastPage);

        return array_map(function (mixed $plan): array {
            if (! is_array($plan)
                || ! is_int($plan['id'] ?? null)
                || ! is_string($plan['code'] ?? null)
                || ! is_string($plan['name'] ?? null)
                || ! array_key_exists('description', $plan)
                || (! is_string($plan['description']) && $plan['description'] !== null)
                || ! is_string($plan['price'] ?? null)
                || preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $plan['price']) !== 1
                || ! is_string($plan['currency'] ?? null)
                || preg_match('/^[A-Z]{3}$/D', $plan['currency']) !== 1
                || ! is_int($plan['duration_days'] ?? null)
                || $plan['duration_days'] < 1
                || ! is_bool($plan['is_active'] ?? null)
                || $plan['is_active'] !== true) {
                throw new UnexpectedValueException('UDEV subscription plan response is invalid.');
            }

            return [
                'id' => $plan['id'],
                'code' => $plan['code'],
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'price' => $plan['price'],
                'currency' => $plan['currency'],
                'duration_days' => $plan['duration_days'],
                'is_active' => true,
            ];
        }, $plans);
    }
}
