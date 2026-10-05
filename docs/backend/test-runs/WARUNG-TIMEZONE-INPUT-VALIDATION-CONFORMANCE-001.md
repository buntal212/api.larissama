# WARUNG-TIMEZONE-INPUT-VALIDATION-CONFORMANCE-001

Status: **PASS**

Task: T-ADM-01/03, D08

Plan commit: `6b0787622a63c6809dc70ba02605e94b111f14fc`

Test commit: `42c217cf3e8dd5d3a94558f0fc6ea0093190e00e`

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-timezone-validation-test`.

## Scope

The test sends `Invalid/Timezone` to superadmin `POST /admin/warungs` and `PATCH /admin/warungs/{id}`. The value is a string within the OpenAPI length bound, so each request body matches the documented request schema; the server must also validate it as an IANA timezone identifier.

Both requests return HTTP 422 `VALIDATION_ERROR` with the response matching OpenAPI and an error on `timezone`. Failed provisioning creates neither a warung nor its owner. Failed update leaves the target warung raw row snapshot unchanged.

## Results

- Focused `AdminWarungApiTest` + `AdminWarungManagementApiTest`: 13 tests / 1,648 assertions, PASS.
- Pint (`--dirty`): PASS.
- Full suite: 371 tests / 56,556 assertions in 37.27 seconds, PASS.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; disposable Compose services removed, `ps -a` empty.
- `git diff --check`: PASS.

No runtime, database schema, or dependency changes were needed. This verifies server-side rejection of an unknown timezone identifier; it does not settle D08's remaining policy for warungs without timezone or backdate/future dates. All operations remain `DRAFT`.

Test sources: [`AdminWarungApiTest.php`](../../../tests/Feature/AdminWarungApiTest.php), [`AdminWarungManagementApiTest.php`](../../../tests/Feature/AdminWarungManagementApiTest.php).
