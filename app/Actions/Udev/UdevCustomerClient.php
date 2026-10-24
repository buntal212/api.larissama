<?php

namespace App\Actions\Udev;

use App\Models\WarungCustomerSync;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class UdevCustomerClient
{
    public function isConfigured(): bool
    {
        $baseUrl = config('services.udev.base_url');
        $token = config('services.udev.token');
        $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && in_array($parts['path'] ?? '/', ['', '/'], true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && is_string($token)
            && $token !== '';
    }

    public function upsert(WarungCustomerSync $sync): Response
    {
        if (! $this->isConfigured()) {
            throw new \LogicException('UDEV customer integration is not configured.');
        }

        return Http::baseUrl(rtrim(config('services.udev.base_url'), '/'))
            ->acceptJson()
            ->withToken(config('services.udev.token'))
            ->connectTimeout(3)
            ->timeout(10)
            ->post('/api/v1/integrations/customers', [
                'external_customer_id' => $sync->external_customer_id,
                'name' => $sync->warung->nama,
                'owner_name' => $sync->owner->nama,
                'email' => $sync->owner->email,
                'phone' => $sync->warung->telepon,
                'status' => 'active',
            ]);
    }
}
