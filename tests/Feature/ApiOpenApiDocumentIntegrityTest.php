<?php

namespace Tests\Feature;

use LogicException;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiOpenApiDocumentIntegrityTest extends TestCase
{
    public function test_document_has_unique_operations_responses_and_resolvable_local_references(): void
    {
        $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
            throw new LogicException('OpenAPI must contain a paths mapping for document integrity.');
        }

        $operationIds = [];
        $operationCount = 0;
        foreach ($document['paths'] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'] as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $operationCount++;
                $operationId = $operation['operationId'] ?? null;
                $this->assertIsString($operationId, "OpenAPI {$method} {$path} must have an operationId.");
                $this->assertNotSame('', $operationId, "OpenAPI {$method} {$path} must have a non-empty operationId.");
                $operationIds[$operationId][] = strtoupper($method).' '.$path;

                $this->assertIsArray($operation['responses'] ?? null, "OpenAPI {$method} {$path} must declare responses.");
                $this->assertNotEmpty($operation['responses'], "OpenAPI {$method} {$path} must declare at least one response.");
            }
        }

        $duplicates = array_filter($operationIds, static fn (array $locations): bool => count($locations) > 1);
        $this->assertSame([], $duplicates, 'OpenAPI operationId values must be unique across the document.');
        $this->assertSame(30, $operationCount, 'The contract contains 28 baseline operations and two approved purchase-correction operations.');
        $this->assertArrayHasKey('updatePembelian', $operationIds);
        $this->assertArrayHasKey('cancelPembelian', $operationIds);

        $references = $this->collectReferences($document);
        $localReferences = array_filter(
            $references,
            static fn (string $reference): bool => str_starts_with($reference, '#/'),
        );
        $this->assertNotEmpty($localReferences, 'The OpenAPI document must contain local component references.');

        $unresolvedReferences = array_filter(
            $localReferences,
            fn (string $reference): bool => ! $this->resolvesLocalJsonPointer($document, $reference),
        );
        $this->assertSame([], $unresolvedReferences, 'Every local OpenAPI JSON Pointer reference must resolve.');
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private function collectReferences(array $value): array
    {
        $references = [];
        foreach ($value as $key => $child) {
            if ($key === '$ref' && is_string($child)) {
                $references[] = $child;
            } elseif (is_array($child)) {
                $references = [...$references, ...$this->collectReferences($child)];
            }
        }

        return $references;
    }

    /**
     * @param  array<array-key, mixed>  $document
     */
    private function resolvesLocalJsonPointer(array $document, string $reference): bool
    {
        $target = $document;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $key = strtr($segment, ['~1' => '/', '~0' => '~']);
            if (! is_array($target) || ! array_key_exists($key, $target)) {
                return false;
            }

            $target = $target[$key];
        }

        return true;
    }
}
