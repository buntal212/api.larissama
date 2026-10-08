# BE-108 Superadmin Transaction Write Conformance

- Date: 2026-10-08
- Decision/task: D04, BE-108
- Database: MySQL 8.0.40, disposable Docker Compose test database
- Result: PASS, focused coverage only

## Coverage

- Superadmin creates paid and pending sales, records payment, corrects and cancels sales, and records a partial return for an explicitly selected warung.
- Superadmin creates, corrects, and cancels purchases for an explicitly selected warung.
- Sale/purchase headers keep tenant `user_id` null and record `created_by_superadmin_id`; payment records `pembayaran_superadmin_id`; audit events record `superadmin_id`.
- Missing target on superadmin create is rejected with 422. A target that does not match the transaction path returns 404. Tenant attempts to submit `warung_id` are rejected with 422 and no transaction is written.
- Superadmin create, payment, correction, and purchase retry paths replay their original record under the actor-and-warung idempotency scope.
- Migration metadata verifies nullable tenant user fields, distinct superadmin actor foreign keys, and separate idempotency unique keys. Rollback refuses to remove actor columns while a superadmin-attributed purchase exists.
- OpenAPI request/response examples and document integrity match the updated contract.

## Verification

Focused command covered `SuperadminTransactionWriteTest`, `ApiOpenApiExamplesConformanceTest`, `ApiOpenApiDocumentIntegrityTest`, `BusinessSchemaMigrationConformanceTest`, `PenjualanApiTest`, `PenjualanCorrectionApiTest`, `SalesOrderPaymentApiTest`, and `PembelianApiTest`.

Result: 73 tests / 35,452 assertions passed. Laravel Pint passed for changed PHP files. OpenAPI 3.1 validation passed with `openapi-spec-validator` 0.9.0.

## Limits

The full G3/G4 regression suite was not run. This result does not certify production deployment or a live frontend integration. Superadmin still has no write permission on tenant catalog or user administration.
