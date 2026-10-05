<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiCorsTest extends TestCase
{
    public function test_quasar_dev_origin_can_preflight_bearer_and_idempotency_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:9000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type,idempotency-key',
        ])->options('/api/v1/penjualans');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:9000');
        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('content-type', $allowedHeaders);
        $this->assertStringContainsString('idempotency-key', $allowedHeaders);
        $this->assertSame('600', (string) $response->headers->get('Access-Control-Max-Age'));
    }

    public function test_unconfigured_browser_origin_is_not_reflected_by_cors(): void
    {
        $response = $this->withHeader('Origin', 'https://untrusted.example')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertSame('http://localhost:9000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('https://untrusted.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }
}
