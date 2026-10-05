<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiRouteOpenApiConformanceTest extends TestCase
{
    #[DataProvider('operationsWithIdPathParameter')]
    public function test_path_ids_match_the_documented_positive_decimal_format(
        string $path,
        string $method,
        string $operationId,
    ): void {
        $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $pathItem = $document['paths'][$path] ?? null;
        $operation = is_array($pathItem) ? ($pathItem[$method] ?? null) : null;
        $this->assertIsArray($operation, "OpenAPI operation {$operationId} must exist.");

        $parameters = array_merge($pathItem['parameters'] ?? [], $operation['parameters'] ?? []);
        $idParameter = null;
        foreach ($parameters as $parameter) {
            if (($parameter['$ref'] ?? null) === '#/components/parameters/Id') {
                $idParameter = $document['components']['parameters']['Id'] ?? null;
                break;
            }

            if (($parameter['name'] ?? null) === 'id' && ($parameter['in'] ?? null) === 'path') {
                $idParameter = $parameter;
                break;
            }
        }

        $this->assertIsArray($idParameter, "OpenAPI operation {$operationId} must reference its required path ID parameter.");
        $this->assertSame('id', $idParameter['name'] ?? null, "OpenAPI operation {$operationId} must use the id parameter.");
        $this->assertSame('path', $idParameter['in'] ?? null, "OpenAPI operation {$operationId} must locate id in the path.");
        $this->assertTrue($idParameter['required'] ?? false, "OpenAPI operation {$operationId} must require its path ID.");
        $schema = $idParameter['schema'] ?? null;
        if (is_array($schema) && isset($schema['$ref'])) {
            $schema = $document['components']['schemas']['Id'] ?? null;
        }

        $this->assertIsArray($schema, "OpenAPI operation {$operationId} must define its ID schema.");
        $this->assertSame('string', $schema['type'] ?? null, "OpenAPI operation {$operationId} must encode IDs as strings.");
        $this->assertSame('^[1-9][0-9]*$', $schema['pattern'] ?? null, "OpenAPI operation {$operationId} must require a positive decimal ID.");

        foreach (['0', '01', 'abc'] as $invalidId) {
            $invalidPath = '/api/v1'.str_replace('{id}', $invalidId, $path);
            $response = $this->json(strtoupper($method), $invalidPath, []);
            $response->assertNotFound();
            $this->assertOperationResponseMatchesOpenApi($response, $path, $method);
        }

        $validPath = '/api/v1'.str_replace('{id}', '1', $path);
        $this->json(strtoupper($method), $validPath, [])
            ->assertUnauthorized();
    }

    public function test_openapi_operations_match_registered_api_routes_one_to_one(): void
    {
        $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
            throw new LogicException('OpenAPI must contain a paths mapping for route conformance.');
        }

        $serverUrl = $document['servers'][0]['url'] ?? null;
        if (! is_string($serverUrl)) {
            throw new LogicException('OpenAPI must declare a server URL for route conformance.');
        }

        $serverPath = parse_url($serverUrl, PHP_URL_PATH);
        if (! is_string($serverPath) || trim($serverPath, '/') === '') {
            throw new LogicException('OpenAPI server URL must contain its API path prefix.');
        }

        $serverPrefix = trim($serverPath, '/');
        $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
        $contractOperations = [];
        $contractPaths = [];

        foreach ($document['paths'] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            $contractPaths[$path] = true;

            foreach ($httpMethods as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;
                if (! is_string($operationId)) {
                    throw new LogicException("OpenAPI {$method} {$path} must have an operationId.");
                }

                $runtimePath = $serverPrefix.'/'.trim($path, '/');
                $key = strtoupper($method).' '.$runtimePath;
                if (array_key_exists($key, $contractOperations)) {
                    throw new LogicException("OpenAPI contains duplicate method/path pair {$key}.");
                }

                $contractOperations[$key] = $operationId;
            }
        }

        $this->assertCount(30, $contractOperations, 'The contract contains 28 baseline operations and two purchase-correction operations.');
        $this->assertCount(19, $contractPaths, 'The contract contains 18 baseline paths and one purchase-cancellation path.');

        $runtimeOperations = [];
        foreach (Route::getRoutes() as $route) {
            $runtimePath = trim($route->uri(), '/');
            if ($runtimePath !== $serverPrefix && ! str_starts_with($runtimePath, $serverPrefix.'/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                $method = strtoupper($method);
                if ($method === 'HEAD') {
                    continue;
                }

                $key = $method.' '.$runtimePath;
                $runtimeOperations[$key][] = $route->getName() ?? $route->getActionName();
            }
        }

        $duplicates = array_filter($runtimeOperations, fn (array $routes): bool => count($routes) !== 1);
        $duplicateDescriptions = [];
        foreach ($duplicates as $key => $routes) {
            $operationId = $contractOperations[$key] ?? 'undocumented';
            $duplicateDescriptions[] = $operationId.' ['.$key.']: '.implode(', ', $routes);
        }

        $this->assertSame(
            [],
            $duplicateDescriptions,
            'Each API method/path pair must be registered exactly once: '.implode('; ', $duplicateDescriptions),
        );

        $contractKeys = array_keys($contractOperations);
        $runtimeKeys = array_keys($runtimeOperations);
        sort($contractKeys);
        sort($runtimeKeys);

        $missingRoutes = array_diff($contractKeys, $runtimeKeys);
        $undocumentedRoutes = array_diff($runtimeKeys, $contractKeys);

        $this->assertSame(
            [],
            $missingRoutes,
            'OpenAPI operations without a Laravel route: '.implode(', ', array_map(
                fn (string $key): string => ($contractOperations[$key] ?? 'unknown')." [{$key}]",
                $missingRoutes,
            )),
        );
        $this->assertSame(
            [],
            $undocumentedRoutes,
            'Laravel API routes missing from OpenAPI: '.implode(', ', $undocumentedRoutes),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function operationsWithIdPathParameter(): array
    {
        $document = Yaml::parseFile(dirname(__DIR__, 2).'/docs/api/openapi.yaml');
        if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
            throw new LogicException('OpenAPI must contain a paths mapping for path ID tests.');
        }

        $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
        $operations = [];
        foreach ($document['paths'] as $path => $pathItem) {
            if (! is_string($path) || ! str_contains($path, '{id}') || ! is_array($pathItem)) {
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

                $operations[$operationId] = [$path, $method, $operationId];
            }
        }

        if (count($operations) !== 12) {
            throw new LogicException('Expected 12 OpenAPI operations with an {id} path parameter.');
        }

        return $operations;
    }
}
