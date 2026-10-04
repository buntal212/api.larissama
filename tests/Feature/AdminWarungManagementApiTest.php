<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class AdminWarungManagementApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|null */
    private ?array $openApiDocument = null;

    public function test_superadmin_can_list_search_filter_paginate_show_and_update_warungs(): void
    {
        $alpha = Warung::factory()->create([
            'kode' => 'WRG-ADM-A',
            'nama' => 'Alpha Market',
            'aktif' => true,
        ]);
        $bravo = Warung::factory()->create([
            'kode' => 'WRG-ADM-B',
            'nama' => 'Bravo Market',
            'aktif' => true,
        ]);
        $inactive = Warung::factory()->create([
            'kode' => 'WRG-ADM-Z',
            'nama' => 'Zulu Market',
            'aktif' => false,
        ]);
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('admin-warung-management-test')->plainTextToken;

        $firstPage = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?page=1&per_page=1&sort=nama')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($firstPage, '/admin/warungs', 'get');
        $this->assertSame([(string) $alpha->id], array_column($firstPage->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $firstPage->json('meta'));

        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?page=2&per_page=1&sort=nama')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($secondPage, '/admin/warungs', 'get');
        $this->assertSame([(string) $bravo->id], array_column($secondPage->json('data'), 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 1, 'total' => 3, 'last_page' => 3], $secondPage->json('meta'));

        $search = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?q=Alpha')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($search, '/admin/warungs', 'get');
        $this->assertSame([(string) $alpha->id], array_column($search->json('data'), 'id'));

        $detail = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs/'.$alpha->id)
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}', 'get');
        $warungResource = $detail->json('data');
        $this->assertEqualsCanonicalizing(
            ['id', 'kode', 'nama', 'alamat', 'telepon', 'logo', 'timezone', 'tanggal_mulai', 'tanggal_berakhir', 'aktif', 'created_at', 'updated_at'],
            array_keys($warungResource),
        );
        $this->assertSame((string) $alpha->id, $warungResource['id']);
        $this->assertSame('WRG-ADM-A', $warungResource['kode']);
        $this->assertTrue($warungResource['aktif']);

        $updated = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, [
                'nama' => 'Alpha Updated',
                'alamat' => 'Jalan Baru 1',
                'timezone' => 'America/New_York',
                'aktif' => false,
            ])
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($updated, '/admin/warungs/{id}', 'patch');
        $this->assertSame('Alpha Updated', $updated->json('data.nama'));
        $this->assertSame('Jalan Baru 1', $updated->json('data.alamat'));
        $this->assertSame('America/New_York', $updated->json('data.timezone'));
        $this->assertFalse($updated->json('data.aktif'));
        $this->assertDatabaseHas('warungs', [
            'id' => $alpha->id,
            'kode' => 'WRG-ADM-A',
            'nama' => 'Alpha Updated',
            'alamat' => 'Jalan Baru 1',
            'timezone' => 'America/New_York',
            'aktif' => false,
        ]);

        $inactiveList = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?aktif=false')
            ->assertOk();
        $this->assertOperationResponseMatchesOpenApi($inactiveList, '/admin/warungs', 'get');
        $this->assertEqualsCanonicalizing(
            [(string) $alpha->id, (string) $inactive->id],
            array_column($inactiveList->json('data'), 'id'),
        );

        $missing = $this->withToken($token)->getJson('/api/v1/admin/warungs/999999999')->assertNotFound();
        $this->assertOperationResponseMatchesOpenApi($missing, '/admin/warungs/{id}', 'get');
        $this->assertD13ErrorEnvelope($missing, 'NOT_FOUND');

        $badPageSize = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?per_page=101')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badPageSize, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($badPageSize, 'VALIDATION_ERROR', 'per_page');

        $badSort = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs?sort=aktif')
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($badSort, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($badSort, 'VALIDATION_ERROR', 'sort');

        $emptyUpdate = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, [])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($emptyUpdate, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($emptyUpdate, 'VALIDATION_ERROR', 'data');

        $invalidDates = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$alpha->id, [
                'tanggal_mulai' => '2026-10-31',
                'tanggal_berakhir' => '2026-10-01',
            ])
            ->assertUnprocessable();
        $this->assertOperationResponseMatchesOpenApi($invalidDates, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($invalidDates, 'VALIDATION_ERROR', 'tanggal_berakhir');
        $this->assertDatabaseHas('warungs', [
            'id' => $alpha->id,
            'nama' => 'Alpha Updated',
            'aktif' => false,
            'tanggal_mulai' => null,
            'tanggal_berakhir' => null,
        ]);
    }

    public function test_openapi_response_schema_check_rejects_an_undocumented_field(): void
    {
        $document = $this->openApiDocument();
        $schema = $this->resolveOpenApiReference($document, '#/components/schemas/WarungResponse');
        $payload = json_decode('{"data":{"id":"1","unexpected":true}}', false, 512, JSON_THROW_ON_ERROR);
        $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unexpected', implode("\n", $errors));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonSuperadminRoles(): array
    {
        return [
            'owner' => ['owner'],
            'manager' => ['manager'],
            'cashier' => ['kasir'],
        ];
    }

    #[DataProvider('nonSuperadminRoles')]
    public function test_tenant_roles_cannot_list_read_or_update_warungs(string $role): void
    {
        $warung = Warung::factory()->create([
            'kode' => 'WRG-ADMIN-LOCKED',
            'nama' => 'Warung Tidak Berubah',
        ]);
        $actor = User::factory()->create(['warung_id' => $warung->id, 'role' => $role]);
        $token = $actor->createToken('admin-warung-management-test')->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/admin/warungs')->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($list, '/admin/warungs', 'get');
        $this->assertD13ErrorEnvelope($list, 'FORBIDDEN');

        $detail = $this->withToken($token)
            ->getJson('/api/v1/admin/warungs/'.$warung->id)
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($detail, '/admin/warungs/{id}', 'get');
        $this->assertD13ErrorEnvelope($detail, 'FORBIDDEN');

        $update = $this->withToken($token)
            ->patchJson('/api/v1/admin/warungs/'.$warung->id, ['nama' => 'Percobaan Ubah'])
            ->assertForbidden();
        $this->assertOperationResponseMatchesOpenApi($update, '/admin/warungs/{id}', 'patch');
        $this->assertD13ErrorEnvelope($update, 'FORBIDDEN');
        $this->assertDatabaseHas('warungs', [
            'id' => $warung->id,
            'kode' => 'WRG-ADMIN-LOCKED',
            'nama' => 'Warung Tidak Berubah',
        ]);
    }

    private function assertOperationResponseMatchesOpenApi(TestResponse $response, string $path, string $method): void
    {
        $document = $this->openApiDocument();
        $operation = $document['paths'][$path][strtolower($method)] ?? null;
        $this->assertIsArray($operation, "OpenAPI operation {$method} {$path} must exist.");

        $responseDefinition = $operation['responses'][(string) $response->getStatusCode()] ?? null;
        $this->assertIsArray($responseDefinition, "OpenAPI must document HTTP {$response->getStatusCode()} for {$method} {$path}.");
        if (isset($responseDefinition['$ref'])) {
            $responseDefinition = $this->resolveOpenApiReference($document, $responseDefinition['$ref']);
        }

        $schema = $responseDefinition['content']['application/json']['schema'] ?? null;
        $this->assertIsArray($schema, "OpenAPI must define an application/json schema for HTTP {$response->getStatusCode()} on {$method} {$path}.");
        $payload = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $errors = $this->collectOpenApiSchemaErrors($payload, $schema, $document, '$');

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    /** @return array<string, mixed> */
    private function openApiDocument(): array
    {
        if ($this->openApiDocument === null) {
            $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
            $this->assertIsArray($document, 'The OpenAPI document must parse to a YAML mapping.');
            $this->openApiDocument = $document;
        }

        return $this->openApiDocument;
    }

    /** @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function resolveOpenApiReference(array $document, string $reference): array
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

    /** @param array<string, mixed> $schema
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function collectOpenApiSchemaErrors(mixed $value, array $schema, array $document, string $path): array
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
            'additionalProperties', 'anyOf', 'description', 'enum', 'format', 'items', 'maximum',
            'maxItems', 'maxLength', 'minimum', 'minItems', 'minLength', 'pattern', 'properties',
            'required', 'title', 'type',
        ];
        $unsupportedKeywords = array_diff(array_keys($schema), $supportedKeywords);
        if ($unsupportedKeywords !== []) {
            return ["{$path} uses unsupported OpenAPI schema keywords: ".implode(', ', $unsupportedKeywords)];
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

    private function assertD13ErrorEnvelope(TestResponse $response, string $expectedCode, ?string $field = null): void
    {
        $body = $response->json();

        $this->assertEqualsCanonicalizing(['code', 'message', 'errors', 'request_id'], array_keys($body));
        $this->assertSame($expectedCode, $body['code']);
        $this->assertIsString($body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $body['request_id'],
        );

        if ($field !== null) {
            $this->assertArrayHasKey($field, $body['errors']);
        }
    }
}
