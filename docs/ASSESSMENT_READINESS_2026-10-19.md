# IRDP E-Clearance: assessment readiness

**Assessment date: 19 October 2026. Status: isolated demonstration available; production migration NOT APPROVED and NOT READY.**

The original application continues using its original database. No cutover or retirement has been performed. The isolated integration is available on this PC at:

http://localhost/irdp_e_clearance/migration_ar/

Use the existing documented accounts privately. Do not project passwords, private configuration, recovery codes or test-fixture credential files during the assessment. Test actions belong exclusively to this isolated URL.

## What can be demonstrated now

1. Sign in as a student. Show the automatically generated test control number, generation date/time and payment status.
2. Upload a test receipt. Clearance cannot start until the assigned Finance officer approves the current receipt.
3. Sign in as the assigned Finance officer and verify the payment. Return as the student and start clearance.
4. Show the ten office stages and locked final Finance stage. Complete office reviews using their assigned officers. Final Finance approval becomes available after all ten approvals.
5. Show the completed certificate PDF and its QR verification. The QR is a local demonstration link; access from another device requires a reachable, correctly configured origin.
6. Show administrator monitoring, weekly/monthly/custom reports, account creation and CSV import preview. Show officer rejection and student correction history.

For a short assessment, use the completed test evidence below instead of rushing through eleven officer approvals. Test records are retained and identifiable; this database must not become production with demonstration fixtures included.

## Recorded validation

Results describe the specific executions recorded in the evidence files, not a claim that every existing application feature has passed a final regression suite.

| Area | Result | Evidence and scope |
| --- | --- | --- |
| Exactly ten tables on MariaDB 10.4.32 | PASS | Core suite: ten InnoDB tables, 35 foreign keys checked with zero orphans; invalid relationship and CHECK mutations rejected. |
| Original snapshot preservation | PASS | All 846 captured rows decrypt and match every original field; all 56 original account hashes and documented credentials validated. This does not include later live writes. |
| Core migration/service suite | WARNING | 31 passed, 2 failed. Both failures are source-snapshot equality guards: the live source no longer equals the captured snapshot. They remain unresolved failures. |
| Primary student/officer/admin browser flow | PASS | 10/10 in `browser-latest.json`, including payment gate, ten offices, final Finance, PDF, role boundaries and mobile layout. This run predates the latest notification/session additions. |
| Additional browser regression | PASS | Latest rerun 10/10 in `browser-extra.json`: account creation, workflow editing, reports, CSV duplicate rejection, payment verification, rejection/correction, closed-period enforcement, profile privacy and recovery issuance. |
| Authentication/recovery security | PASS | Latest rerun 10/10 in `security.json`: password rules, OTP attempts/cooldown/replay protection, token replacement, session-version revocation and inactive-account denial. |
| Email worker | PASS | Latest rerun 7/7 in `mail.json` using fake delivery only: retry/backoff, no replay, recipient-change cancellation and English content. Real SMTP remains disabled and untested. |
| Certificate QR | PASS | Actual browser-downloaded PDF rasterized with Windows PDF engine and independently decoded using jsQR; verification HTTP request succeeded (`qr-winrt.json`). |
| Backup restoration | PASS | Separate restore rehearsal recovered 30 original tables, 846 rows and 297 files with comparisons (`restore.json`). Original database was not restored over or dropped. |
| PHP syntax | PASS | 25 integration/private PHP files checked on this assessment pass; zero syntax failures. |
| Current-source reconciliation | FAIL | Later source activity has not been migrated into a final clean target. No production cutover is safe yet. |
| Complete feature parity and deployment readiness | WARNING | Final compatibility, regression and operational checks below remain open. |

Evidence is stored locally under `storage/private/ar-integration/results/`. Earlier failed runs are retained. A PDF drawing defect and the CSV duplicate-error display were corrected in the isolated integration; the QR scan and additional browser rerun subsequently passed.

## Ten-table design used by the isolated integration

| Table | Responsibility |
| --- | --- |
| organisation_units | Departments and offices with typed identities |
| programmes | Programme catalogue and references |
| clearance_cycles | Academic cycles, selected clearance period and policy |
| users | Accounts, student attributes and authentication/recovery state |
| office_memberships | Officer-to-office assignments |
| clearance_requests | Student clearance and entry-payment state |
| clearance_stages | Ordered office reviews and final Finance state |
| documents | Receipts, evidence and certificate records/snapshots |
| activity_log | Immutable typed events and encrypted source-row archives |
| notifications | Notifications and delivery state |

Archival preservation is not a substitute for proving every operational query and report. Existing report equivalence passed eight captured-data date ranges in the core suite. Current production totals will differ until subsequent source activity is reconciled.

## Remaining release gates

- Finish exhaustive application/URL compatibility and feature-parity validation, including historical certificate access, notification pagination and all supported import paths.
- Complete final browser regression after the latest notification and session changes, including session timeout/renewal and the additional Finance debt/payment correction path.
- Document and verify the complete column-level migration mapping, including recovery from interruption between database commit and migration sidecar publication.
- Reconcile all live changes using a fresh consistent source snapshot and a clean target without fixtures. Source writes require an agreed maintenance window for the final migration.
- Configure and validate the final public origin, certificate links, scheduled workers and delivery policy. Real email has deliberately not been sent during tests.
- Preserve an off-machine backup with separately protected recovery keys; the successful local restore rehearsal alone is insufficient disaster recovery.
- Obtain explicit user approval only after all critical tests pass. Keep old-database retirement as a separate approval decision.

## Backup and rollback boundary

The verified local backup is `storage/private/ar-integration/backups/20261019-131846-17b6d9`. Its separate restore database is `irdp_ar_restore_20261019_ec5956`; it is a recovery rehearsal, not the application's target database. The ten-table integration target is `irdp_ar_proto_20261019`.

Before any approved cutover, capture a fresh source database/files/configuration backup, verify restoration, freeze writes for final reconciliation, and preserve the original configuration. Before new-system writes, rollback can return to the preserved original application/database. After new-system writes, simply switching back would lose those new transactions: a tested reconciliation procedure is required first. Never automatically drop the original database.

## Assessment statement

“The isolated ten-table integration demonstrates the payment-first clearance workflow, ten office reviews, locked final Finance approval, certificate QR verification and tested administration/security flows. The original system remains operational. Production migration is pending source reconciliation, final parity/regression checks and explicit approval.”

