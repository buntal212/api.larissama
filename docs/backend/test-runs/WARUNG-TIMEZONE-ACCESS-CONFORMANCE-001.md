# WARUNG-TIMEZONE-ACCESS-CONFORMANCE-001

Status: **PASS**

Task: T-AUTH-02/04, D08 (provisional behavior)

Plan commit: `d8a280b59a8e12f9b37307ee405cfb7066bf7dde`

Test commit: `0fa5cfb735c0d67650d666a0fe82eac465b62505`

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-wz-test`.

## Scope

`Warung::allowsAccessAt()` currently denies tenant access when the warung timezone is NULL or is not a recognized IANA identifier. The test covers both values for each tenant role: owner, manager, and kasir.

For all six role/timezone cases, login returns HTTP 403 `FORBIDDEN` matching the OpenAPI response schema. A bearer token created before the probe also receives 403 from `GET /auth/me` and `GET /warung`; both responses match OpenAPI. Login does not issue a new token, and the existing token remains stored. The login request body is checked against the documented request schema.

## Results

- Focused `AuthApiTest`: 26 tests / 3,627 assertions, PASS.
- Pint (`--dirty`): PASS.
- Full suite: 369 tests / 56,416 assertions in 37.57 seconds, PASS.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; disposable Compose services removed, `ps -a` empty.
- `git diff --check`: PASS.

This verifies the provisional fail-closed implementation and its documented HTTP behavior. It does not finalize the business decision for legacy warungs without a timezone or the backdate/future-date policy. D08 remains `PARTIAL`; no runtime, OpenAPI, database schema, or dependency changes were needed. All operations remain `DRAFT`.

Test source: [`AuthApiTest.php`](../../../tests/Feature/AuthApiTest.php).
