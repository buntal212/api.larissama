# First superadmin bootstrap command

- Date: 2026-10-06
- Scope: BE-104/G1 initial access on the confirmed fresh/empty production database.
- Added `php artisan app:bootstrap-superadmin` with interactive secret prompts; no password argument or default credential is provided.
- Safety: MySQL advisory lock, hard guard that the entire `users` table is empty, lowercase unique username/email, minimum eight character confirmed password, active `superadmin` with `warung_id = NULL`; model stores a password hash.
- `DatabaseSeeder` no longer inserts Laravel's obsolete `Test User` row.
- Focused `BootstrapSuperadminCommandTest`: 4 tests / 35 assertions, PASS. Covered creation/hash/role/tenant fields, denial when any user exists, uppercase username, short password, and mismatched confirmation with no write.
- Full suite: 534 tests / 81,620 assertions, PASS in 49.45 seconds on disposable MySQL 8.0.40.
- Pint: PASS, 189 files. OpenAPI 3.1 validator: PASS in the dedicated disposable Docker container.
- Test and validator Compose projects were removed after the run. No production credentials or user rows were created. The command is documented in README and RUNBOOK.
