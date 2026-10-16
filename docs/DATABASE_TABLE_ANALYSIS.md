# Database table analysis

Current status: the nine-table cleanup was completed on 13 October 2026.
The live database now has **29 tables and 33 accounts**, retaining all existing
features. See [cleanup details](NINE_TABLE_CLEANUP.md).

The analysis below records the earlier 38-table inspection, before cleanup.
Its account counts and removal recommendations describe that earlier snapshot.

Reviewed on 13 October 2026 using read-only queries against the local
`irdp_e_clearance` database at `127.0.0.1:3306` and the application source.
No application code or database records were changed for this analysis.

## Recommendation

The inspected database has **38 base tables**, rather than the approximately
42 reported. **29 tables support the currently implemented functions**, including
the reachable prototype student import page and retained certificate support.
**Nine tables are candidates for removal** because their features have been
retired and they have no runtime references in the reviewed application code.

For only the current navigation features plus existing certificate compatibility,
the target is **28 tables**, after also disabling the prototype import route.
This is a recommendation for this implementation, not a universal minimum for
a clearance system.

## Users and responsibilities

| Role | Total accounts | Active accounts | Current responsibilities |
| --- | ---: | ---: | --- |
| Student | 15 | 15 | Profile, entry-fee payment, clearance requests, corrections/evidence, notifications, transcript downloads |
| Officer | 11 | 11 | Assigned office reviews and decisions; Finance also manages entry fees and debt payment review |
| Administrator | 1 | 1 | Accounts, recovery requests, programmes, departments, offices, workflow, clearance periods, monitoring, reports, transcript revocation |
| Supervisor | 0 | 0 | Supported role that uses the officer dashboard and assigned office permissions |
| **Total** | **27** | **27** | |

All roles share `users` and `roles`. Students need `students` for their student
details. Officers, administrators and supervisors do not need separate account
tables. Office review authority comes from `user_offices`; the Supervisor role
does not require the retired `office_supervisors` table.

The eleven offices are rows in `offices` and `workflow_steps`, rather than eleven
separate office tables. Finance debt receipts use the existing stage evidence
and review history; entry-fee payments have their own four tables because they
occur before clearance starts.

## Nine removal candidates

| Table | Exact live rows | Reason it is unnecessary for the current runtime |
| --- | ---: | --- |
| `officer_availability` | 0 | Availability routing was removed; reviewers are selected through active office membership. |
| `officer_backup` | 0 | Delegation and backup routing were removed. Legacy migrations still add columns and update this table. |
| `clearance_escalations` | 0 | Escalation processing was removed and the former deadline job exits without accessing the database. |
| `emergency_clearances` | 0 | Emergency review was removed; the old page redirects. |
| `office_supervisors` | 0 | Separate supervisory authority configuration was removed; current reviewers use office membership. |
| `assignment_history` | 0 | The retired assignment-history table has no runtime readers or writers; current assignment changes are audited through `audit_logs`. |
| `stage_events` | 0 | Reminder/deadline event processing was removed. |
| `stage_appeals` | 0 | Appeal processing was removed; students resubmit corrections through review cycles and evidence. |
| `app_settings` | 3 | Its remaining reminder, escalation and resubmission-limit settings have no runtime readers. |

These conclusions rely on tracing source references and feature routes, not
just on empty tables. All nine still appear in installation or migration code.
The inspected database has no views, triggers, stored routines or scheduled
database events. Its declared foreign keys show no retained table referencing
any of these nine candidates.

## Tables to retain

Each table appears once below. The groups total **29**.

| Purpose | Count | Tables |
| --- | ---: | --- |
| Accounts and reference data | 8 | `roles`, `users`, `students`, `departments`, `programmes`, `offices`, `user_offices`, `workflow_steps` |
| Office reviews and corrections | 5 | `clearance_requests`, `clearance_stages`, `stage_actions`, `review_cycles`, `stage_evidence` |
| Academic cycles and opening periods | 2 | `academic_cycles`, `clearance_period` |
| Clearance entry fee | 4 | `clearance_fee_rates`, `clearance_fee_payments`, `clearance_fee_receipts`, `clearance_fee_actions` |
| Notifications | 2 | `notifications`, `email_outbox` |
| Password recovery and abuse controls | 3 | `password_resets`, `password_reset_requests`, `rate_limits` |
| Issued documents | 2 | `transcripts`, `certificates` |
| Audit and schema tracking | 2 | `audit_logs`, `schema_migrations` |
| Prototype student import | 1 | `import_runs` |
| **Total** | **29** | |

`password_reset_requests` records the administrative identity-verification
workflow. `password_resets` stores hashed, expiring reset credentials. They
serve different purposes. Similarly, `stage_actions`, `review_cycles` and
`stage_evidence` preserve decisions, student responses and files separately.

`notifications` holds in-system messages, while `email_outbox` tracks SMTP
delivery and retries. Removing the Email Notifications admin screen did not
remove notification delivery.

`schema_migrations` is required by the normal startup readiness check; removing
it can cause setup to run again. Admin reports query existing operational tables
and do not need a separate reports table. Profile image metadata is stored in
`students`, so no profile pictures table is required.

Many necessary operational tables are currently empty because this database
has no clearance requests or issued documents. That does not make them redundant.

## Conditional reductions

| Target | What must change |
| --- | --- |
| **29 tables** | Remove the nine retired-feature tables and update installer/migrations while retaining all currently implemented routes. Recommended default. |
| **28 tables** | Also retire `admin/import.php`, its import commit/logging path and associated tests, then remove `import_runs`. The route works even though it is absent from the menu. |
| **27 tables** | Also retire certificate issuance, download and verification support, then remove `certificates`. Clearance completion currently calls `issue_certificate()`, so removing only the table would break completion. |

Legacy `student_results`, `programme_modules`, `grading_rules` and `sms_outbox`
are absent from the inspected database. Results support was removed; fresh
installations do not create the results tables. They therefore do not contribute
to this database's table count.

## Evidence in the repository

- `includes/installer.php`: creates the four retired availability, delegation,
  escalation and emergency tables, and uses `schema_migrations` for startup.
- `includes/migrations.php`: creates the other five candidates; also references
  `officer_backup` during upgrades.
- `includes/clearance_workflow.php`: enforces current reviewer authority and uses
  review cycles, evidence, decisions, certificate issuance and transcripts.
- `includes/functions.php`: selects active office reviewers, audits assignment
  changes and routes Supervisors to the officer dashboard.
- `includes/header.php`: defines the current navigation for each role.
- `admin/import.php` and `includes/imports.php`: still implement import and write
  to `import_runs`.
- `certificates/download.php`, `certificates/verify.php` and
  `includes/functions.php`: retain certificate functionality.
- `jobs/clearance_deadlines.php`: confirms deadline processing was removed.
- `docs/FEATURES.md`: documents the retired features, retained certificate
  compatibility and prototype import.

## Requirements for a later cleanup

Back up the database, remove the retired table definitions and legacy migration
references, and introduce a versioned cleanup migration. Otherwise a setup or
repair run will recreate dropped tables; `officer_backup` and `app_settings`
also have legacy migration statements that need adjustment. Update tests that
create retired records to test compatibility, then verify fresh installation,
upgrade, accounts, review/resubmission, Finance payments, notifications, password
recovery and document completion in an isolated database.

This review covers the inspected local database and repository. It cannot
establish whether separately maintained external scripts use these tables.
No tables have been deleted.
