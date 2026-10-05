<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiOpenApiExamplesConformanceTest extends TestCase
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

    public function test_operation_request_and_response_examples_match_their_openapi_schemas(): void
    {
        $document = $this->openApiDocument();
        $exampleCount = 0;

        foreach ($document['paths'] as $path => $pathItem) {
            foreach (self::METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $operationName = strtoupper($method).' '.$path;
                if (isset($operation['requestBody'])) {
                    $requestBody = $this->resolveMaybeOpenApiReference($document, $operation['requestBody']);
                    $exampleCount += $this->assertContentExamplesMatchSchemas(
                        $requestBody['content'] ?? [],
                        $document,
                        $operationName.' requestBody',
                    );
                }

                foreach ($operation['responses'] ?? [] as $status => $responseDefinition) {
                    $response = $this->resolveMaybeOpenApiReference($document, $responseDefinition);
                    $exampleCount += $this->assertContentExamplesMatchSchemas(
                        $response['content'] ?? [],
                        $document,
                        $operationName." response {$status}",
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $exampleCount, 'OpenAPI operations must provide request or response examples.');
    }

    public function test_parameter_examples_match_their_openapi_schemas(): void
    {
        $document = $this->openApiDocument();
        $exampleCount = 0;

        foreach ($document['components']['parameters'] ?? [] as $name => $parameter) {
            $this->assertIsArray($parameter);
            $exampleCount += $this->assertParameterExamplesMatchSchema(
                $parameter,
                $document,
                "components.parameters.{$name}",
            );
        }

        foreach ($document['paths'] as $path => $pathItem) {
            foreach ($pathItem['parameters'] ?? [] as $index => $parameter) {
                if (isset($parameter['$ref'])) {
                    continue;
                }

                $exampleCount += $this->assertParameterExamplesMatchSchema(
                    $parameter,
                    $document,
                    "{$path}.parameters.{$index}",
                );
            }

            foreach (self::METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                foreach ($operation['parameters'] ?? [] as $index => $parameter) {
                    if (isset($parameter['$ref'])) {
                        continue;
                    }

                    $exampleCount += $this->assertParameterExamplesMatchSchema(
                        $parameter,
                        $document,
                        strtoupper($method)." {$path}.parameters.{$index}",
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $exampleCount, 'OpenAPI parameters must provide schema-conformant examples.');
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $document
     */
    private function assertContentExamplesMatchSchemas(array $content, array $document, string $location): int
    {
        $checked = 0;
        foreach ($content as $mediaType => $media) {
            if (! is_array($media)) {
                continue;
            }

            $examples = [];
            if (array_key_exists('example', $media)) {
                $examples['example'] = $media['example'];
            }

            foreach ($media['examples'] ?? [] as $name => $exampleDefinition) {
                $example = $this->resolveMaybeOpenApiReference($document, $exampleDefinition);
                $this->assertArrayHasKey(
                    'value',
                    $example,
                    "OpenAPI example {$location} {$mediaType}.{$name} must have an inline value for offline conformance.",
                );
                $examples[(string) $name] = $example['value'];
            }

            if ($examples === []) {
                continue;
            }

            $schema = $media['schema'] ?? null;
            $this->assertIsArray($schema, "OpenAPI examples at {$location} {$mediaType} must declare a schema.");

            foreach ($examples as $name => $value) {
                $payload = $this->normalizeExampleValue($value, $schema, $document);
                $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');
                $this->assertSame(
                    [],
                    $errors,
                    "OpenAPI example {$location} {$mediaType}.{$name} does not match its schema: ".implode('; ', $errors),
                );
                $checked++;
            }
        }

        return $checked;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @param  array<string, mixed>  $document
     */
    private function assertParameterExamplesMatchSchema(array $parameter, array $document, string $location): int
    {
        $examples = [];
        if (array_key_exists('example', $parameter)) {
            $examples['example'] = $parameter['example'];
        }

        foreach ($parameter['examples'] ?? [] as $name => $exampleDefinition) {
            $example = $this->resolveMaybeOpenApiReference($document, $exampleDefinition);
            $this->assertArrayHasKey(
                'value',
                $example,
                "OpenAPI parameter example {$location}.{$name} must have an inline value.",
            );
            $examples[(string) $name] = $example['value'];
        }

        if ($examples === []) {
            return 0;
        }

        $schema = $parameter['schema'] ?? null;
        $this->assertIsArray($schema, "OpenAPI parameter examples at {$location} must declare a schema.");

        foreach ($examples as $name => $value) {
            $normalizedValue = $this->normalizeExampleValue($value, $schema, $document);
            $errors = $this->collectOpenApiSchemaErrors($normalizedValue, $schema, $document, '$');
            $this->assertSame(
                [],
                $errors,
                "OpenAPI parameter example {$location}.{$name} does not match its schema: ".implode('; ', $errors),
            );
        }

        return count($examples);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $document
     */
    private function normalizeExampleValue(mixed $value, array $schema, array $document): mixed
    {
        if (isset($schema['$ref'])) {
            return $this->normalizeExampleValue(
                $value,
                $this->resolveOpenApiReference($document, $schema['$ref']),
                $document,
            );
        }

        foreach (['oneOf', 'anyOf'] as $unionKeyword) {
            if (! isset($schema[$unionKeyword])) {
                continue;
            }

            $bestValue = $value;
            $fewestErrors = PHP_INT_MAX;
            foreach ($schema[$unionKeyword] as $branch) {
                $candidate = $this->normalizeExampleValue($value, $branch, $document);
                $errors = $this->collectOpenApiSchemaErrors($candidate, $branch, $document, '$');
                if ($errors === []) {
                    return $candidate;
                }
                if (count($errors) < $fewestErrors) {
                    $bestValue = $candidate;
                    $fewestErrors = count($errors);
                }
            }

            return $bestValue;
        }

        if (($schema['type'] ?? null) === 'object' && is_array($value)) {
            $properties = $schema['properties'] ?? [];
            $additionalProperties = $schema['additionalProperties'] ?? true;
            $normalized = [];
            foreach ($value as $key => $child) {
                $childSchema = $properties[$key] ?? (is_array($additionalProperties) ? $additionalProperties : null);
                $normalized[$key] = is_array($childSchema)
                    ? $this->normalizeExampleValue($child, $childSchema, $document)
                    : $child;
            }

            return (object) $normalized;
        }

        if (($schema['type'] ?? null) === 'array' && is_array($value) && isset($schema['items'])) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizeExampleValue($item, $schema['items'], $document),
                $value,
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function resolveMaybeOpenApiReference(array $document, mixed $definition): array
    {
        if (is_array($definition) && isset($definition['$ref'])) {
            return $this->resolveOpenApiReference($document, $definition['$ref']);
        }

        $this->assertIsArray($definition);

        return $definition;
    }
}
