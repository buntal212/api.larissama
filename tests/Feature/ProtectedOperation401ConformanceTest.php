<?php

namespace Tests\Feature;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ProtectedOperation401ConformanceTest extends TestCase
{
    #[DataProvider('protectedOperations')]
    public function test_anonymous_request_returns_schema_conformant_401(
        string $path,
        string $method,
        string $operationId,
    ): void {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][$method] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$operationId} must exist.");
        $effectiveSecurity = $operation['security'] ?? $document['security'] ?? [];

        $this->assertNotEmpty($effectiveSecurity, "OpenAPI operation {$operationId} must require authentication.");
        $this->assertArrayHasKey('401', $operation['responses'] ?? [], "OpenAPI operation {$operationId} must document HTTP 401.");

        $requestPath = preg_replace('/\{[^}]+\}/', '1', $path);
        $this->assertIsString($requestPath);
        $response = $this->json(strtoupper($method), '/api/v1'.$requestPath, []);

        $response->assertUnauthorized();
        $response->assertJsonPath('code', 'UNAUTHENTICATED');
        $requestId = $response->json('request_id');
        $this->assertIsString($requestId);
        $this->assertNotSame('', $requestId);
        $this->assertOperationResponseMatchesOpenApi($response, $path, $method);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function protectedOperations(): array
    {
        $document = Yaml::parseFile(dirname(__DIR__, 2).'/docs/api/openapi.yaml');
        if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
            throw new LogicException('OpenAPI must contain a paths mapping for authenticated route tests.');
        }

        $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
        $operations = [];

        foreach ($document['paths'] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach ($httpMethods as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;
                if (! is_string($operationId)) {
                    throw new LogicException("OpenAPI {$method} {$path} must have an operationId.");
                }

                if ($operationId === 'login') {
                    continue;
                }

                $operations[$operationId] = [$path, $method, $operationId];
            }
        }

        if ($operations === []) {
            throw new LogicException('OpenAPI must contain protected operations to verify.');
        }

        return $operations;
    }
}
