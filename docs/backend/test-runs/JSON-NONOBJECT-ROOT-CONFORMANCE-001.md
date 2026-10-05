# JSON-NONOBJECT-ROOT-CONFORMANCE-001

Status: **PASS**

Task: T-API-02/04, D13

Plan commit: `465d65afd4ef1ad72f510699d691f42607733ee8`

Test commit: `a56c9dc8b112454b3a39684182d9110db48d4478`

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-json-root-test`.

## Scope

The request body schemas for all 11 body-bearing operations require a JSON object. The feature test sends six syntactically valid but non-object roots to each operation: `null`, an empty array, an array containing a value, a string, a number, and a boolean. Protected requests use valid bearer tokens and target resources; sale and purchase include valid `Idempotency-Key` headers.

All 66 requests are rejected by their OpenAPI request schema and return HTTP 422 `VALIDATION_ERROR`. Every response has nonempty `errors`, a UUID `request_id`, and a body matching the documented Error422 schema. Tracked business table counts and snapshots of the target warung, user, category, and menu are unchanged.

## Results

- Focused `ApiMalformedJsonConformanceTest`: 3 tests / 2,386 assertions, PASS.
- Pint (`--dirty`): PASS.
- Full suite: 363 tests / 55,858 assertions in 36.17 seconds, PASS.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; disposable Compose services removed, `ps -a` empty.
- `git diff --check`: PASS.

The first focused attempt returned 429 on a later login case because every case shared one test IP and the approved login limiter allows five attempts per minute per username/IP. Each JSON root case now uses a distinct reserved test IP; the rerun and full suite passed. No runtime, OpenAPI, database schema, or dependency change was needed. Overall T-API-02/04 remains open and all operations remain `DRAFT`.

Test source: [`ApiMalformedJsonConformanceTest.php`](../../../tests/Feature/ApiMalformedJsonConformanceTest.php).
