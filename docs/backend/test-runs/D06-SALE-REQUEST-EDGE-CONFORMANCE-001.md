# D06 sale correction request edge conformance

- Task/test: BE-306, G3, D06/D09/D13
- Test commit: `be6c957`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none; this slice extends request conformance coverage only.

## Coverage

The three sale mutation operations are included in the shared validators for malformed JSON, missing bodies, and non-object JSON roots:

- `PATCH /api/v1/penjualans/{id}`
- `POST /api/v1/penjualans/{id}/pembatalan`
- `POST /api/v1/penjualans/{id}/retur`

The tests confirm malformed JSON returns schema-conformant 400 `BAD_REQUEST`; absent bodies and JSON roots `null`, arrays, strings, numbers, and booleans return schema-conformant 422 `VALIDATION_ERROR`; no business or audit rows are written. Sale mutation requests containing `warung_id` are rejected with 422 before changing sale or audit history. Their `Idempotency-Key` header rejects missing, empty, whitespace-edge, and 256-character values; a 255-character value succeeds with 201 and one audit event.

## Results

- Focused: 9 tests / 6,489 assertions, PASS.
- Full suite: 524 tests / 79,421 assertions in 48.08 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

Commands:

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'vendor/bin/pint --test tests/Feature/ApiMalformedJsonConformanceTest.php tests/Feature/ApiRequestUnknownFieldsConformanceTest.php tests/Feature/IdempotencyKeyHeaderConformanceTest.php && php artisan test --no-progress tests/Feature/ApiMalformedJsonConformanceTest.php tests/Feature/ApiRequestUnknownFieldsConformanceTest.php tests/Feature/IdempotencyKeyHeaderConformanceTest.php'
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'vendor/bin/pint --test && php artisan test --no-progress'
docker compose -f compose.test.yaml --project-name larissama-backend-test down --remove-orphans
```

## Boundary

This adds request edge cases for the three D06 write operations. It does not cover every request field/value, success/error status, role-state combination, or environment handoff. All operation contracts remain `DRAFT`; frontend may continue schema/mock work, but live integration is not marked ready.
