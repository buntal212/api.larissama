<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use LogicException;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiRouteOpenApiConformanceTest extends TestCase
{
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

        $this->assertCount(28, $contractOperations, 'The approved contract inventory contains 28 operations.');
        $this->assertCount(18, $contractPaths, 'The approved contract inventory contains 18 paths.');

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
}
