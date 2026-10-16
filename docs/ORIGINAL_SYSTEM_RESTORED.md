# Original system restored — 13 October 2026

After this restoration, nine unused tables were removed with no feature changes.
The current database has 29 tables; see [cleanup details](NINE_TABLE_CLEANUP.md).

The user requested restoration of the features and pages present before the
ten-table redesign. The original 121 source files were restored from
`storage/private/backups/before-ten-tables-20261013-072759-source.zip`.
The restored application uses the original 38-table schema, including separate
student profiles, workflow configuration, bulk import, password recovery,
clearance histories, fee records, transcripts and email queue.

All 33 current accounts, password hashes, emails, profiles and office assignments
were retained. The six recently added students are included. Authentication
versions were incremented so existing sessions must log in again. The current
cycle/period configuration and 18 login/account-creation audit records were
transferred. No clearance requests, payments, documents or notifications existed
in the ten-table database at restoration time.

The original schema was imported into a separate staging database and populated
with current account data before the atomic table swap. The first pre-redesign
backup database remains untouched. The full ten-table database is also retained
as `irdp_core_recovery_9ca38bad`. No application database tables reference either
backup database.

Rollback material is private:

- `storage/private/backups/before-original-restore-20261013.sql`
- `storage/private/backups/before-original-restore-20261013-source.zip`
- `storage/private/backups/original-restore-20261013.json`

The current PHP executable discovery fix in `jobs/run_email_worker.ps1` was kept;
it is needed for this XAMPP installation. CSS and uploaded files were preserved.
Obsolete ten-table implementation files were removed after archiving.

Run `tests/documented_demo_accounts_test.php` for a read-only check of all 33
published credentials. Optional username arguments exercise Apache login and
role dashboards. The original `tests/features_test.php`, validation and password
accessibility tests now apply again. SMTP delivery itself was not exercised.
