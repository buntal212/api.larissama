<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, mixed>|null */
    protected ?array $openApiDocument = null;

    protected function assertOperationResponseMatchesOpenApi(TestResponse $response, string $path, string $method): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][strtolower($method)] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$method} {$path} must exist.");

        $responseDefinition = $operation['responses'][(string) $response->getStatusCode()] ?? null;
        $this->assertIsArray($responseDefinition, "OpenAPI must document HTTP {$response->getStatusCode()} for {$method} {$path}.");
        if (isset($responseDefinition['$ref'])) {
            $responseDefinition = $this->resolveOpenApiReference($document, $responseDefinition['$ref']);
        }

        $content = $responseDefinition['content'] ?? null;
        if ($content === null) {
            $this->assertSame('', $response->getContent(), "OpenAPI response for {$method} {$path} has no body schema, so its HTTP body must be empty.");

            return;
        }

        $schema = $content['application/json']['schema'] ?? null;
        $this->assertIsArray($schema, "OpenAPI must define an application/json schema for HTTP {$response->getStatusCode()} on {$method} {$path}.");
        $payload = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $headers
     */
    protected function assertOperationRequestMatchesOpenApi(array $body, array $headers, string $path, string $method): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][strtolower($method)] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$method} {$path} must exist.");

        $requestBody = $operation['requestBody'] ?? null;
        $this->assertIsArray($requestBody, "OpenAPI must define a requestBody for {$method} {$path}.");
        $schema = $requestBody['content']['application/json']['schema'] ?? null;
        $this->assertIsArray($schema, "OpenAPI must define an application/json request schema for {$method} {$path}.");

        $payload = json_decode(json_encode((object) $body, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');
        $this->assertSame([], $errors, implode("\n", $errors));

        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveOpenApiReference($document, $parameter['$ref']);
            }

            if (($parameter['in'] ?? null) !== 'header') {
                continue;
            }

            $headerKey = null;
            foreach (array_keys($headers) as $candidate) {
                if (strcasecmp($candidate, $parameter['name']) === 0) {
                    $headerKey = $candidate;
                    break;
                }
            }

            if ($headerKey === null) {
                $this->assertFalse(
                    $parameter['required'] ?? false,
                    "Required OpenAPI header {$parameter['name']} must be supplied for {$method} {$path}.",
                );

                continue;
            }

            $parameterSchema = $parameter['schema'] ?? null;
            if ($parameterSchema === null) {
                continue;
            }

            $headerValue = json_decode(json_encode($headers[$headerKey], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            $parameterErrors = $this->collectOpenApiSchemaErrors($headerValue, $parameterSchema, $document, "header.{$parameter['name']}");
            $this->assertSame([], $parameterErrors, implode("\n", $parameterErrors));
        }
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function assertOperationQueryMatchesOpenApi(array $query, string $path, string $method): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][strtolower($method)] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$method} {$path} must exist.");

        $queryParameters = [];
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveOpenApiReference($document, $parameter['$ref']);
            }

            if (($parameter['in'] ?? null) === 'query') {
                $queryParameters[$parameter['name']] = $parameter;
            }
        }

        foreach ($query as $name => $value) {
            $this->assertArrayHasKey($name, $queryParameters, "Query parameter {$name} must be declared for {$method} {$path}.");
            $schema = $queryParameters[$name]['schema'] ?? null;
            $this->assertIsArray($schema, "OpenAPI query parameter {$name} must define a schema.");

            $typedValue = $this->parseOpenApiQueryValue($value, $schema, "query.{$name}", $document);
            $errors = $this->collectOpenApiSchemaErrors($typedValue, $schema, $document, "query.{$name}");
            $this->assertSame([], $errors, implode("\n", $errors));
        }

        foreach ($queryParameters as $name => $parameter) {
            if ($parameter['required'] ?? false) {
                $this->assertArrayHasKey($name, $query, "Required OpenAPI query parameter {$name} must be supplied for {$method} {$path}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $document
     */
    private function parseOpenApiQueryValue(string $value, array $schema, string $path, array $document): mixed
    {
        if (isset($schema['$ref'])) {
            $schema = $this->resolveOpenApiReference($document, $schema['$ref']);
        }

        $type = $schema['type'] ?? null;
        if ($type === 'integer') {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            $this->assertNotFalse($integer, "{$path} must serialize as an integer query value.");

            return $integer;
        }

        if ($type === 'number') {
            $number = filter_var($value, FILTER_VALIDATE_FLOAT);
            $this->assertNotFalse($number, "{$path} must serialize as a numeric query value.");

            return $number;
        }

        if ($type === 'boolean') {
            $this->assertContains($value, ['true', 'false'], "{$path} must serialize as true or false.");

            return $value === 'true';
        }

        if ($type === 'string') {
            return $value;
        }

        $this->fail("{$path} uses unsupported OpenAPI query parameter type.");

        return $value;
    }

    /** @return array<string, mixed> */
    protected function openApiDocument(): array
    {
        if ($this->openApiDocument === null) {
            $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
            $this->assertIsArray($document, 'The OpenAPI document must parse to a YAML mapping.');
            $this->openApiDocument = $document;
        }

        return $this->openApiDocument;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function resolveOpenApiReference(array $document, string $reference): array
    {
        $this->assertStringStartsWith('#/', $reference, 'Only local OpenAPI references are supported in this test.');
        $resolved = $document;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $segment);
            $this->assertIsArray($resolved);
            $this->assertArrayHasKey($key, $resolved, "OpenAPI reference {$reference} must resolve.");
            $resolved = $resolved[$key];
        }

        $this->assertIsArray($resolved, "OpenAPI reference {$reference} must resolve to a mapping.");

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    protected function collectOpenApiSchemaErrors(mixed $value, array $schema, array $document, string $path): array
    {
        if (isset($schema['$ref'])) {
            return $this->collectOpenApiSchemaErrors(
                $value,
                $this->resolveOpenApiReference($document, $schema['$ref']),
                $document,
                $path,
            );
        }

        $supportedKeywords = [
            'additionalProperties', 'anyOf', 'const', 'default', 'description', 'enum', 'format', 'items', 'maximum',
            'maxItems', 'maxLength', 'minimum', 'minItems', 'minLength', 'pattern', 'properties',
            'oneOf', 'required', 'title', 'type',
        ];
        $unsupportedKeywords = array_diff(array_keys($schema), $supportedKeywords);
        if ($unsupportedKeywords !== []) {
            return ["{$path} uses unsupported OpenAPI schema keywords: ".implode(', ', $unsupportedKeywords)];
        }

        if (isset($schema['oneOf'])) {
            $matchingBranches = 0;
            foreach ($schema['oneOf'] as $branch) {
                if ($this->collectOpenApiSchemaErrors($value, $branch, $document, $path) === []) {
                    $matchingBranches++;
                }
            }

            return $matchingBranches === 1
                ? []
                : ["{$path} must match exactly one of its OpenAPI oneOf schemas; matched {$matchingBranches}"];
        }

        if (isset($schema['anyOf'])) {
            $branchErrors = [];
            foreach ($schema['anyOf'] as $branch) {
                $errors = $this->collectOpenApiSchemaErrors($value, $branch, $document, $path);
                if ($errors === []) {
                    return [];
                }
                $branchErrors[] = implode('; ', $errors);
            }

            return ["{$path} must match one of its OpenAPI anyOf schemas: ".implode(' | ', $branchErrors)];
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            return ["{$path} does not match OpenAPI const"];
        }

        if (isset($schema['type'])) {
            $types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
            $matchesType = false;
            foreach ($types as $type) {
                if ($this->openApiTypeMatches($value, $type)) {
                    $matchesType = true;
                    break;
                }
            }

            if (! $matchesType) {
                return ["{$path} does not match OpenAPI type ".implode('|', $types)];
            }
        }

        $errors = [];
        if (is_object($value)) {
            $properties = $schema['properties'] ?? [];
            foreach ($schema['required'] ?? [] as $requiredProperty) {
                if (! property_exists($value, $requiredProperty)) {
                    $errors[] = "{$path}.{$requiredProperty} is required";
                }
            }

            foreach (get_object_vars($value) as $property => $propertyValue) {
                if (array_key_exists($property, $properties)) {
                    array_push($errors, ...$this->collectOpenApiSchemaErrors(
                        $propertyValue,
                        $properties[$property],
                        $document,
                        "{$path}.{$property}",
                    ));

                    continue;
                }

                $additionalProperties = $schema['additionalProperties'] ?? true;
                if ($additionalProperties === false) {
                    $errors[] = "{$path}.{$property} is an undocumented property";
                } elseif (is_array($additionalProperties)) {
                    array_push($errors, ...$this->collectOpenApiSchemaErrors(
                        $propertyValue,
                        $additionalProperties,
                        $document,
                        "{$path}.{$property}",
                    ));
                }
            }
        } elseif (is_array($value)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $errors[] = "{$path} has fewer items than OpenAPI minItems";
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $errors[] = "{$path} has more items than OpenAPI maxItems";
            }
            if (isset($schema['items'])) {
                foreach ($value as $index => $item) {
                    array_push($errors, ...$this->collectOpenApiSchemaErrors($item, $schema['items'], $document, "{$path}[{$index}]"));
                }
            }
        } elseif (is_string($value)) {
            $length = mb_strlen($value);
            if (isset($schema['minLength']) && $length < $schema['minLength']) {
                $errors[] = "{$path} is shorter than OpenAPI minLength";
            }
            if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
                $errors[] = "{$path} is longer than OpenAPI maxLength";
            }
            if (isset($schema['pattern']) && preg_match('~'.str_replace('~', '\\~', $schema['pattern']).'~u', $value) !== 1) {
                $errors[] = "{$path} does not match OpenAPI pattern";
            }
            if (isset($schema['format']) && ! $this->openApiStringFormatMatches($value, $schema['format'])) {
                $errors[] = "{$path} does not match OpenAPI format {$schema['format']}";
            }
            if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
                $errors[] = "{$path} is not in the OpenAPI enum";
            }
        } elseif (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = "{$path} is below OpenAPI minimum";
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = "{$path} is above OpenAPI maximum";
            }
        }

        return $errors;
    }

    private function openApiTypeMatches(mixed $value, string $type): bool
    {
        return match ($type) {
            'array' => is_array($value),
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'null' => $value === null,
            'number' => is_int($value) || is_float($value),
            'object' => is_object($value),
            'string' => is_string($value),
            default => false,
        };
    }

    private function openApiStringFormatMatches(string $value, string $format): bool
    {
        if ($format === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($format === 'date') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = \DateTimeImmutable::getLastErrors();

            return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format('Y-m-d') === $value;
        }

        if ($format === 'date-time') {
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$/', $value) !== 1) {
                return false;
            }

            try {
                new \DateTimeImmutable($value);

                return true;
            } catch (\Exception) {
                return false;
            }
        }

        return false;
    }
}
