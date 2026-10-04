# TRANSACTION-READ-001

Tanggal: 2026-10-04 (Asia/Jakarta)
Task: BE-302, BE-303, BE-402, BE-403
Test IDs: T-SAL-04 (partial), T-TEN-02 (partial), T-BUY-05 (covered)
Commit yang diuji: `a7b5ffa`
Status run: **PASS**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- DB identity: disposable `test-db` service, database `larissama_test`, user `larissama_test`; no host port or persistent volume. The runner was configured to connect to this service and database before migrations/tests. No development or production DB was used.
- The test DB container and Compose network were stopped and removed after the run.

## Commands and results

```sh
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Result |
| --- | --- |
| Compose validation | PASS, exit code 0 |
| Pint | PASS; sorted imports in `PembelianApiTest.php` |
| Changed feature files | PASS, 16 tests / 77 assertions, no warnings, 1.68 seconds |
| Full `composer test` suite | PASS, 21 tests / 95 assertions, no warnings, 3.04 seconds |
| Test stack cleanup | PASS; disposable DB container and network removed |

Expected: valid history reads preserve original snapshot values, cross-tenant detail lookups do not disclose another warung's transaction, and neither purchase input shape mutates sales/menu data. Actual matches all three expectations.

## Scenario evidence and limits

| Test ID | Status | Evidence |
| --- | --- | --- |
| T-SAL-04 | PARTIAL | Create a sale from `Nasi Goreng Awal` at `15000.00`; change master name and price; `GET` detail still returns original name, price and subtotal while the menu row has the new values. A new sale after the edit was not included; D05 price behavior remains provisional. |
| T-TEN-02 | PARTIAL | A manager from warung A receives 404 for sale and purchase IDs belonging to warung B. The records remain present for B. Broader role/detail combinations are not covered. |
| T-BUY-05 | PASS for tested behavior | Submit one summary purchase and one detailed purchase. Database ends with two purchase headers / three purchase details, while the existing sale, its snapshot detail and menu values remain unchanged. |

This run does not close G3/G4, full T-TEN-02, OpenAPI runtime conformance, or any open business decision. All API operations remain DRAFT.
