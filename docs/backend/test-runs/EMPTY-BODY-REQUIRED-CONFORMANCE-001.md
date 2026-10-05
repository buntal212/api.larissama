# EMPTY-BODY-REQUIRED-CONFORMANCE-001

Status: **PASS**

Task: T-API-02/04, D13

Plan commit: `faed2c5425e8f75d1a79692b4500ee290f739e4c`

Test commit: `67e2dcd4bc455bceeefc5be508242a4f2a2dd7b6`

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-empty-body-test`.

## Scope

The feature test sends an actual zero-byte `application/json` body to all 11 OpenAPI operations that declare `requestBody.required: true`: login; POST/PATCH warung admin, user, category, and menu; and POST sale/purchase. Protected operations use an authorized bearer token and valid target resource. Sale and purchase include valid `Idempotency-Key` headers. The test also checks the OpenAPI `required` flag for each operation.

All 11 requests return HTTP 422 `VALIDATION_ERROR`, with nonempty `errors`, a UUID `request_id`, and an Error422 response matching the OpenAPI schema. Business table counts and snapshots of the target warung, user, category, and menu are unchanged.

## Results

- Focused `ApiMalformedJsonConformanceTest`: 2 tests / 598 assertions, PASS.
- Pint (`--dirty`): PASS.
- Full suite: 362 tests / 54,070 assertions in 37.50 seconds, PASS.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; disposable Compose services removed, `ps -a` empty.
- `git diff --check`: PASS.

The evidence is limited to missing-body handling for these 11 operations and does not complete overall T-API-02/04. No runtime, OpenAPI, database schema, or dependency changes were needed; every operation remains `DRAFT`.

Test source: [`ApiMalformedJsonConformanceTest.php`](../../../tests/Feature/ApiMalformedJsonConformanceTest.php).
