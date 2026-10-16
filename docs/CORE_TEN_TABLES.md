# Ten-table core clearance system

Historical documentation: this version was rolled back on 13 October 2026.
The active application retains the original features with 33 accounts and,
after removing unused legacy tables, 29 tables. See [cleanup details](NINE_TABLE_CLEANUP.md).

The retired version used exactly ten base tables, with no compatibility views:

1. `users`: Student, Officer and Administrator accounts, student profile fields,
   validated role and a single office for each officer.
2. `departments`: department reference data.
3. `programmes`: programme reference data.
4. `offices`: eleven offices, their stage number, title and configuration.
5. `clearance_cycles`: academic cycles, selected opening period and fee rate.
6. `clearance_requests`: a draft entry-fee request followed by started clearance;
   retains the quoted fee, current control number, receipt and payment version.
7. `clearance_stages`: independent current office decisions and review cycles.
8. `activity_log`: append-only decisions, responses, payment changes and audits.
9. `documents`: private evidence/receipts and immutable transcript snapshots.
10. `notifications`: internal messages with email delivery/retry state.

## Workflow

Students request a fee control number while the selected period is open, upload
a receipt and wait for Finance approval. Uploading a receipt does not start
clearance. A draft becomes started only after the current receipt is approved;
then all eleven office stages are created as pending. Officers review independently.
Rejected offices require student corrections and optional evidence. Finance debt
control numbers and receipts are separate from the entry-fee fields. Stale payment
versions and review cycles cannot approve replacement receipts or submissions.
The last required approval issues a clearance transcript with all eleven approvals.

Administrators manage accounts, programmes, departments, offices, periods,
monitoring, reports and transcript revocation. Office configuration is now on the
Offices page; the old Workflow URL redirects there. Profile pictures, contact
details, optional student password changes and email notifications remain.

Legacy certificate issuance, bulk import, Supervisor controls and the recovery
request/token queue are retired. Their URLs redirect without processing former
actions. Admins can reset an account under Users only after recording identity
verification and supplying their own current password. Resets revoke existing
sessions; staff must change a replacement password. Password hashes remain
Argon2id for new passwords; existing hashes continue to work.

Session expiry remains 30 minutes idle and eight hours absolute. Login/reset
throttling uses locked files under `storage/private/limits`, shared by PHP workers
and scoped to the database. This deployment assumes one application host.
Retain private storage across process restarts. Multi-host deployment would
need a shared throttling store. Email sending still uses SMTP settings in
`config/mail.local.php` and `jobs/send_notification_emails.php`.

## Installation and migration

Fresh installation creates only these ten tables. The users table comment stores
the schema version; startup reads schema metadata instead of a migrations table.
Running `setup.php` or `jobs/migrate.php` checks readiness and never recreates
retired tables. `database.sql` contains only the current non-destructive DDL.

The explicit legacy converter handles a database whose clearance, payment,
document and history tables are empty. It refuses history or unsupported
multiple-office/Supervisor assignments, rather than discarding them. The reviewed
local database met these conditions. Account IDs, passwords, profiles, reference
data and opening-period configuration are preserved. Student IDs become the
corresponding account IDs; no legacy operational records existed to remap.
Existing login sessions are revoked through an incremented authentication version.

Prepare with `php jobs/core_migrate.php --prepare`. This creates a new staging
database and a manifest in private backups. Verify the staging copy and application,
then use `php jobs/core_migrate.php --apply <manifest path>` during maintenance.
The apply step rechecks the source fingerprint and account preservation, and
atomically renames both table sets in one MariaDB statement. The entire original
38-table schema remains in a separate `irdp_core_backup_*` database. SQL and source
backups also remain under `storage/private/backups`. The active database has ten
tables; the separate original database is retained solely for rollback.

The local upgrade completed on 13 October 2026. The retained original database
is `irdp_core_backup_719ceaa3`. Post-upgrade checks confirmed ten base tables,
no views, 15 Students, 11 Officers, one Administrator and no foreign keys pointing
outside the active database. Landing, login, setup and diagnostics returned HTTP 200.

To roll back, stop requests, restore the original source archive and atomically
move the active core tables into a separate recovery database while moving the
original backup tables back into the application database. Do not overlay the
old dump onto an active system containing new clearance activity.

## Verification

Use PHP 8.2 with PDO MySQL, mbstring, fileinfo and curl; GD or the existing Windows
image-resize helper supports profiles. On this machine the CLI executable is
`C:/xampp1/php/windowsXamppPhp/php.exe`; explicitly load the extensions or the
XAMPP ini because that relocated executable does not find php.ini by default.

Run `tests/core_schema_smoke.php`, `tests/core_migration_test.php` and
`tests/core_clearance_test.php`. The latter covers HTTP permissions, CSRF, fee
approval, private receipts, stale versions, multiple rejected offices, evidence,
Finance debt payment, all eleven approvals, transcript stability/PDF/verification/
revocation, account creation, profiles, integrated email state, password changes,
admin reset and session expiry. Each test creates and removes disposable databases.
The migration test also restores the saved legacy fixture into a disposable database.

Set `CORE_TEST_REAL_ROUTES=1` when running the HTTP suite to test the actual PHP
entry points instead of the draft router. `tests/features_test.php` now invokes
this suite. Other tests that reference retired tables describe the former schema;
use the current core suite for database-dependent regression coverage. The
password/accessibility and image-resize checks remain applicable.

SMTP transport integration is preserved but delivery to an external provider
requires configured credentials and is not established by the mocked sender test.
