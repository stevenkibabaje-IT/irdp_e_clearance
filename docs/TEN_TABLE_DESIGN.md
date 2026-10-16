# Ten-table clearance design

Historical design, rolled back on 13 October 2026 at the user's request.
The active database has 29 tables and 33 accounts after targeted cleanup. See
[cleanup details](NINE_TABLE_CLEANUP.md).

## Scope

The user selected **Simplify to core clearance**. Ten tables can support that
scope through consolidation, with three roles: Student, Officer and Administrator.
The application queries, page routes, permissions and installer have been
refactored to use this schema.

## Proposed tables

| Number | Table | Purpose and consolidation |
| ---: | --- | --- |
| 1 | `users` | All accounts, role, password hash, student registration/profile fields and reviewer office assignment. Combines `users`, `students`, `roles` and a single-office membership model. |
| 2 | `departments` | Department reference data and departmental reviewer restrictions. |
| 3 | `programmes` | Programme reference data and programme administration. |
| 4 | `offices` | Office details, stage number, stage title and review configuration. Combines offices and workflow steps. |
| 5 | `clearance_cycles` | Academic cycle, manual/scheduled opening period, remarks and default entry-fee rate. Combines academic cycles, clearance period and fee rates. |
| 6 | `clearance_requests` | One student/cycle record, entry-fee amount, assigned Finance reviewer, control number, payment version, payment state and subsequent clearance state. Combines the entry-fee payment and clearance request. |
| 7 | `clearance_stages` | Eleven independent office reviews per started request, current reviewer, review cycle, status, liabilities and corrective instructions. |
| 8 | `activity_log` | Append-only typed records for office decisions, student responses, fee decisions/control-number changes and administrative audit events. Combines decisions, review-cycle history, payment history and auditing. |
| 9 | `documents` | Typed evidence files, entry-fee receipts and immutable issued clearance transcript snapshots with verification/revocation metadata. Combines file and issued-document storage. |
| 10 | `notifications` | In-system messages; optional email recipient, queue state, delivery attempts and retry timestamps in the same row. Combines notifications and the email outbox. |

Roles become a validated value on `users`; they do not need a lookup table.
User records must enforce registration-number uniqueness for students and
require student-specific attributes only for the Student role.

## Required workflow changes

- Create a draft student/cycle request before entry-fee payment. A draft is not
  a started clearance and must not bypass the payment gate. Start timestamps
  and eleven office stages are created only after Finance approval and an open
  period. Existing requests retain their existing fee exemption.
- Store the quoted fee on the request, so later cycle-rate changes do not alter
  already issued amounts. Track control-number and receipt versions explicitly.
- Preserve every review and student response as an append-only activity record.
  A new cycle changes the current stage state but must not erase earlier events.
- Validate document type and ownership on uploads and downloads. A fee receipt
  belongs to a request/payment version; evidence belongs to a stage/review cycle;
  an issued transcript belongs to a completed request.
- Preserve immutable transcript snapshots, verification tokens, approval details
  and revocation history independently from later changes to user records.
- Keep private file storage, transaction locking and server-side permission checks.
  Fewer tables must not change approval requirements or permit stale receipt approval.

## Simplified scope and implementation constraints

The core target retains account administration, profile/password changes,
departments/programmes, eleven independent office reviews, clearance periods,
entry fees and Finance debt checks, evidence/resubmission, notifications,
clearance transcripts, audit history and reports from operational data.

The core scope excludes the self-service password-recovery request queue,
legacy certificates, prototype bulk import and separate Supervisor controls,
in addition to the nine already retired database features. Authentication
protections still need these implementation changes:

- **Password recovery:** retire the existing request/token pages and their menu
  links. Any direct administrator password reset must verify identity, record an
  audit event and revoke existing sessions. Password changes remain supported.
- **Rate limiting:** retain shared, atomic login/recovery throttling. Moving it to
  a file or another service requires implementation and deployment support;
  browser-session counters alone are insufficient.
- **Schema versioning:** replace database migration tracking with a reliable
  installation/upgrade mechanism before retiring `schema_migrations`.
- **Multiple office membership:** use one `office_id` per reviewer. This covers
  the inspected eleven officer accounts and narrows the old many-office model.
- **Workflow configurability:** embedding a step in an office assumes one office
  per stage in the current eleven-office process. The current configuration can
  reference offices from independently configured workflow steps.
- **Certificates:** keep the clearance transcript and retire certificate issuance,
  download and verification endpoints. The inspected database has no certificates.
- **Prototype import:** retire its routes and retain ordinary account creation.

Login throttling and migration tracking are technical requirements even though
their existing tables are absent from the target. They must have working
replacements before switching the application to ten tables.

## Migration approach

Implement the schema and rewritten application against an isolated copy first.
Create an explicit mapping from every existing table and column, preserve original
IDs where needed, validate row counts and historical events, and test every retained
workflow. Back up the original database and switch only after these checks pass.
Dropping twenty-eight existing tables directly would break the current application.
