# Nine unused tables removed — 13 October 2026

The application now uses 29 tables and retains all existing features and 33
accounts. Removed tables: `officer_availability`, `officer_backup`,
`clearance_escalations`, `emergency_clearances`, `office_supervisors`,
`assignment_history`, `stage_events`, `stage_appeals`, and `app_settings`.
Eight tables were empty; `app_settings` contained only the three retired
reminder, escalation and resubmission-limit settings.

Their creation, column updates and seed statements were removed from the
installer and feature migration. Runtime schema version is now
`2026_runtime_v14_retired_tables`. The cleanup migration refuses nonempty legacy
history, unknown setting names and incoming foreign keys from retained tables.
It validates all candidates before dropping any and is safe to repeat.

Private backups:

- `storage/private/backups/before-nine-table-cleanup-20261013.sql`
- `storage/private/backups/before-nine-table-cleanup-20261013-source.zip`
- `storage/private/backups/nine-table-cleanup-before.json`
- `storage/private/backups/nine-table-cleanup-after.json`

The live cleanup compared row counts and full-row hashes for every retained data
table. All rows, passwords, profiles, payments and notifications were unchanged.
Only schema migration tracking gained new version entries. The active database
has no foreign keys pointing to backup databases.

The isolated feature suite passed all 309 HTTP requests, covering Finance fees
and debt receipts, all eleven office approvals, corrections/evidence, transcripts,
import, password recovery, permissions and profiles. It also verifies fresh
installation with 29 tables, upgrade from the nine legacy tables, refusal to
delete legacy history/unknown settings, and no recreation after repeated setup.
All 33 live published credentials were checked, with real Apache login/dashboard
checks for a student, administrator and Finance officer after cleanup.

Normal upgrades continue to use `jobs/migrate.php`. The base `database.sql`
already omitted all nine retired tables and requires the normal feature
migrations to complete installation. SMTP delivery was not exercised.
