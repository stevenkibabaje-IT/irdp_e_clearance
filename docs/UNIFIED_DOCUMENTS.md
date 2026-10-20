# Certificate storage: 30 tables to 29

Subsequent update (2026-10-20): the empty `import_runs` table was also retired, bringing the current total to **28 tables**. Importing remains supported; its per-student and batch outcome events continue in `audit_logs`. Setup no longer creates `import_runs` and refuses to drop it if historical rows have not been archived. The original document-consolidation details below describe the first reduction.

`transcripts` now stores certificate identity and versioned document snapshots. The `certificates` table is copied, verified and removed by `2026_unified_documents_v1`. No compatibility view or replacement table is created.

## Identity and versions

- `certificate_number`, `verification_code`, `issued_at` retain the original certificate identity on one row per clearance request. A unique generated `certificate_request_id` prevents a second identity for the same request.
- `legacy_certificate_id` preserves the old certificate row ID. Existing transcript IDs stay unchanged so audit document references remain valid.
- If a historical certificate already has document versions, identity is attached to its earliest existing row. Other versions retain their original IDs, numbers, tokens, statuses and snapshot bytes.
- A historical certificate without a document snapshot has nullable document fields. Migration does not invent historical office approvals, an issuer or a verification token. Its first legitimate document generation fills that row after normal clearance checks.
- New completion first issues identity, then generates the snapshot in the same request transaction. The two operations fill one row. Subsequent document versions use additional rows without duplicating certificate identity.
- Snapshot lookup excludes identity-only rows. Legacy number/code verification checks the latest document's revocation and integrity when a snapshot exists.

## Compatible features

Existing `/certificates/verify.php`, `/certificates/download.php?id=...`, `/transcripts/verify.php?token=...` and `/transcripts/download.php` URLs continue working. Request ownership, all eleven approvals, cleared liabilities and document revocation remain enforced by their respective routes. Reports read identity and latest document from the same table; weekly/monthly PDFs remain available.

## Migration and validation

Backup before migration: `storage/private/normalization-backup/before-document-merge.sql`. The directory denies HTTP access through `.htaccess`; keep this sensitive backup private.

The migration runs under the installer's schema lock. MariaDB DDL is not transactional: added columns/indexes may persist after an interrupted upgrade. Record copying uses a transaction and checks legacy IDs, request IDs, certificate numbers, verification codes and issue dates before dropping the source table. Completed migration is recorded in `schema_migrations`; rerunning setup does not recreate `certificates`.

Checks:

```powershell
C:/xampp/php/php.exe tests/unified_documents_test.php
C:/xampp/php/php.exe tests/automatic_entry_fee_test.php
C:/xampp/php/php.exe tests/reporting_test.php
C:/xampp/php/php.exe jobs/database_audit.php
```

The migration test restores the full pre-merge backup to an isolated random database, checks all old transcript fields and certificate identities, includes a legacy-only record, repeats migration and forces setup again. The other tests check new issuance, old/new downloads and verification, revocation, Finance locking, and all 14 report pages plus CSV/PDF exports.

## Recovery

Stop application writes before any rollback. Restore the pre-merge SQL backup into an isolated database first, verify it, then switch to matching pre-merge application code and restore the intended database. A full rollback discards changes made after the backup unless they are reconciled first. Do not restore an old backup over newer records while the application is accepting writes.

The earlier `DATABASE_NORMALIZATION_REVIEW.md` describes the schema at the time of its audit. This document records the subsequent first consolidation; the other table groups remain separate.
