# Final architectural assessment — second review

Date: 18 October 2026. Scope: assessment only; implementation requires explicit approval.

Reviewed proposal: [DATABASE_FINANCE_REVIEW_2026-10-18.md](DATABASE_FINANCE_REVIEW_2026-10-18.md).

**Overall implementation readiness: FAIL.** The proposed business workflow is sound, but the original ten-table proposal is not sufficiently specified or validated to authorize a production migration. Its statement that it “preserves” all features should be read as a design intention, not a demonstrated result. This assessment supersedes that assurance.

**The intentionally removed features remain excluded.** Do not restore backup delegation, emergency clearance, time-based escalation, officer-availability routing or appeals. Retained legacy columns/history are not authorization to rebuild those features. The existing SUPERVISOR role continues to mean ordinary assigned-office authority, not a restored escalation system.

No application code, database tables or existing records were changed for this review. Only this assessment document was created. No migration, DDL experiment, account login, credential reset or live email was performed.

## Assessment grades

PASS means the architectural rule is sound or the stated inspection result is verified. WARNING means feasible with explicit conditions and additional evidence. FAIL means a material implementation blocker remains; it does not mean existing production data has been damaged.

| Critical area | Grade | Assessment |
|---|---|---|
| 1. Preservation of every operational feature | WARNING | Responsibilities can be mapped, but recovery history, old identifiers and template/instance queries need complete contracts. No end-to-end migrated application exists. |
| 2a. Payment approval and locked Finance final-review sequence | PASS | Separate payment approval, prerequisite lock and final approval are logically correct. |
| 2b. Workflow transaction specification and legacy transition | WARNING | Exact global lock ordering, current-payment invariants and handling of the already-approved active Finance stage remain to be specified/tested. |
| 3. Private-file throttling as originally proposed | FAIL | “Locked files on one host” is insufficient evidence for this threaded Windows/Apache deployment; crash consistency and bounded storage are also unspecified. |
| 4. Nullable columns, event JSON and table responsibilities | WARNING | Several coherent consolidations exist, but users, stages and the event ledger become unusually complex. Strong subtype contracts and narrower event roles are mandatory. |
| 5a. Existing data and account integrity | PASS | Rechecked 56 active accounts, 46 declared FK relationships, certificate snapshot hashes and six referenced evidence files; no FK orphan or file/hash error found. |
| 5b. Proven migration without data loss | FAIL | There is no executable converter, full column/value reconciliation, verified restore or reverse migration rehearsal. A schema inventory is not a backup. |
| 6. MariaDB 10.4.32 implementability | WARNING | Required primitives exist, but the exact DDL has not been executed. Nullable composite constraints, strict SQL mode and immediate FK ordering require corrections/proof. |
| 7. Constraints, links and feature migration safety | WARNING | The risks are identifiable; source-ID compatibility, report projections, credential lifecycle and installer behavior must be resolved before cutover. |
| 8. Report accuracy after consolidation | WARNING | Precise query-equivalence rules are given below. Existing SQL cannot be retained unchanged, and migrated results have not been compared. |
| 9. Safest maintainable ten-table option | WARNING | A revised original design is the least disruptive ten-table candidate, conditional on the security/constraint/report gates below. No compared ten-table option earns an unconditional PASS. |
| Intentionally removed features stay removed | PASS | Explicitly excluded from all recommended implementation scope. |
| Production implementation approval | FAIL | Do not implement the first proposal as written. Review this assessment and approve the revised direction before any implementation work. |

## 1. Evidence and limits

Read the proposed report, schema inventory, source dependency index, authentication/recovery services, payment and review services, document verification, admin/officer dashboards, monitoring, imports and the actual report generator. Rechecked live metadata and aggregate values through `config/database.php` in a read-only transaction; avoided application bootstrap because it can migrate on startup.

Fresh observations:

- MariaDB **10.4.32**, 30 base tables, 46 declared FKs; zero orphans across those declared relationships.
- **56 users, all active:** 43 students, 12 officers, 1 admin; 0 supervisor accounts. All 56 password hashes have a recognized PHP hash algorithm. This verifies hash format, not a login attempt or knowledge of the plaintext password.
- 4 requests: 2 IN_PROGRESS and 2 COMPLETED; 44 stages, 46 review cycles and 33 stage actions.
- 9 entry payments, 4 entry receipts, 26 fee-history records; 2 stage-evidence records.
- Both stored certificate snapshot hashes match their original bytes. All six evidence/receipt files exist, match recorded byte sizes and can be SHA-256 hashed. This is not proof of a prior trusted content checksum or a restored backup.
- 2 legacy certificates and 2 current Student Clearance Certificate snapshots; 233 audit rows, 199 notifications and 58 email delivery rows.
- 12 office memberships, 11 workflow templates, 10 programmes, 2 departments and 2 cycles.
- 1 OTP row, 0 reset-token rows and 0 administrator recovery-request rows. Empty recovery tables do not remove the requirement to preserve their working features.
- `foreign_key_checks=1` and `check_constraint_checks=1` on the inspected connection.
- SQL mode is `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION`: **strict transactional mode is not enabled on that connection**. A converter must not rely on invalid values always causing hard errors.
- Apache reports WinNT MPM, **threaded**, and `httpd-xampp.conf` loads `php8apache2_4.dll`. The PHP CLI reports 8.2.12 with ZTS enabled. Configuration inspection is not a concurrency stress test of live requests.

No target schema, migration or workflow was run. The earlier 829 browser-layout checks validate the previous UI work only and cannot be used to certify this architecture.

## 2. Operational feature preservation

| Current feature and source | Preservation contract | Remaining issue |
|---|---|---|
| Registration-number and staff login; `auth/login.php`, `includes/authentication.php` | Same account IDs/usernames/hash bytes, active flags, roles, auth version, password-change policy and session expiry | New student-ID joins must not read account IDs as old student IDs; decide whether cutover deliberately expires existing sessions |
| Student profile, phone, picture and email recovery | Single canonical identity with retained source values, private-file permissions and correct owner | Earlier inspection found one empty-vs-populated email copy; preserve both source values, not a silent bulk overwrite |
| Student OTP and reset | Separate current OTP and reset credential state, generation IDs, expiry, attempts, delivery state, browser binding and single-use semantics | A new OTP can coexist with a previously issued reset until the existing invalidation rules act; one generic token column would break this |
| Admin-assisted recovery; `admin/recovery.php` | OPEN/ALL/PENDING/ISSUED/COMPLETED/REJECTED filters, pagination, selected request links, reasons and request identity | “Current state on users; history in events” is incomplete unless historical requests can still be listed and selected by their original identity |
| Users, offices, departments, programmes and imports | All catalogs, validations, multi-office membership, import accounting and links retained | Replacing programme administration with a static enum/file is not equivalent |
| Workflow editor; `admin/workflow.php` | All 11 slots, titles, active state and configurable office mappings remain usable | Queries need template-only filters; old instances need frozen rules. Do not assume one office can occur in only one slot |
| Period and fee settings | Manual/scheduled/closed modes, selected cycle, EAT times, rate defaults and historical quotes | At-most-one selected cycle is not exactly-one. Unconfigured/closed state must remain representable |
| Payment preparation, evidence and Finance verification | Stable demo number/date/time/quote, receipt replacement, current version, assigned reviewer | New approval gate/rejection are intentional changes; do not accidentally alter payment authority or issue real GePG references |
| Office review and correction | Same assigned reviewer/department checks, liability forms, round history, explanations and evidence | Final Finance rejection needs reason-only correction as well as receipt correction; missing payment demand must not force a payment |
| In-app and English email notifications | Read state, original message, delivery recipient/status/retries and direct OTP delivery | Worker queries must not send old SENT rows or expose encrypted recovery history |
| Certificates and verification | Both legacy code and token URLs, original snapshot bytes, hashes, versions and validity | Legacy verification checks current request eligibility; snapshot verification checks stored document/hash. Do not replace both with one changed verification rule |
| Reporting and dashboards | Preserve each query's entity/count/date semantics, including historical repeated decisions | A raw event count or all-document count will be wrong; see section 7 |
| Central auditing and deployment readiness | Actor/time/details/history, migration history, installer compatibility | Append-only grants, event schemas, version bootstrap and preservation of all source provenance remain unimplemented |

**Conclusion:** every current feature has a potential destination, but that is insufficient to award PASS for preservation. The first design also needs an explicit request-history projection for recovery, not merely archive retention. This is a correction to that proposal.

## 3. Finance workflow validation

The logical sequence is correct:

`payment evidence → Finance payment APPROVED → student starts → ten offices review → Finance final review unlocks → Finance final APPROVED → COMPLETED`.

Required independent state dimensions:

| Dimension | States/meaning |
|---|---|
| Entry payment | PENDING with phase AWAITING_EVIDENCE or AWAITING_VERIFICATION; REJECTED with reason; APPROVED for a particular reference/receipt/version |
| Application | Payment draft, ready to start, in progress, paused, completed; retain existing legacy terminal states |
| Office stage | LOCKED, PENDING/IN_REVIEW, REJECTED, APPROVED |
| Additional Finance debt | Its own reference, receipt/version and verification state; never reuse the entry-payment approval flag |

Important boundary cases and corrections:

1. An open period and an approved payment are both necessary for Start; Finance approving payment while the period is closed must not start clearance automatically.
2. Unlock requires the **complete expected set of ten distinct non-Finance stage instances**, each approved, plus the current approved entry payment. Zero unfinished rows is insufficient if rows are missing. Duplicate template/instance rows must not pass the gate.
3. A stage can be eligible for final review yet still have unresolved Finance debt. Eligibility is not approval. Keep the existing no-other-debt path and existing debt receipt flow.
4. Keep the existing post-start immutability of the entry control/receipt. The first report's broad “replacement clears approval” rule applies to allowed pre-start replacements; it must not introduce post-start replacement/refund/reopening functionality.
5. Recheck the gate in the backend for every final decision, including direct POSTs. A Finance payment reviewer cannot approve another office merely by submitting its stage ID. For an explicitly multi-office officer, authority is evaluated for the assigned stage; Finance membership alone gives no other-office authority.
6. A final rejection requires reason/instructions and a new correction cycle. The student cannot edit an APPROVED stage; previously approved offices remain unchanged. New Finance rejection labels must also be reflected in dashboard “awaiting student” counts.
7. Exactly one successful final approval may issue completion documents. Use application/version checks and unique command/document keys, not a frontend disabled button.
8. Existing completed records stay historical. The earlier inspection identified one active request whose Finance stage was already approved. Preserve that decision and its timestamp; a new final-review cycle and explicit policy-transition event are needed, not an overwritten approval. Reinspect at the future cutover.
9. The first report says to use consistent locks but leaves contradictory starting points: account-first for payment/start and request-first then account for certificate generation. That is not a complete global lock order. Define one hierarchy for all account/cycle/application/stage/document operations, or eliminate unnecessary cross-path locks, and test opposing concurrent operations. This must be settled before implementation.
10. Request serialization handles last-office/unlock races only if **every mutating path follows it**, including period/rate interactions, receipt verification and migration transitions. No bypass through a shared helper is allowed.

Finance-last business logic receives PASS; the transaction design remains WARNING until these contracts are formalized and tested. None of the intentionally retired features is needed to implement this sequence.

## 4. Rate limiting: security reassessment

The existing SQL limiter provides shared atomic counter updates. Moving its state outside SQL changes a security boundary. One host alone does not establish correctness: PHP documents a threading caveat for `flock()`, directly relevant to the inspected Apache configuration. The original file-lock proposal therefore fails the architectural readiness gate. [PHP flock documentation](https://www.php.net/manual/en/function.flock.php).

Required protections for any replacement:

- All Apache threads, processes and applicable CLI callers share the same namespace and atomic read-check-increment operation. A per-session counter, process-local mutex or separate file per browser is unacceptable.
- Preserve purpose/identity normalization, thresholds, window semantics and all existing unexpired counters. Throttling state must not roll back when the authentication transaction fails.
- Keep paths outside the served document root where possible, with restrictive Windows ACLs. `chmod(0700)` or `.htaccess` alone is not proof of correct NTFS permissions. Never use raw usernames/emails as filenames; use a fixed namespace and digest and reject path traversal/reparse-point redirection.
- Prevent lost increments, truncated writes and stale replacements. Specify durable writing/recovery on Windows, not an assumed Unix rename behavior. Treat corrupt/unreadable state as a controlled denial rather than an empty counter.
- Lock timeout, storage failure, permissions failure and exhausted space must fail closed for the protected action, with bounded time and an operational error. An unavailable limiter must not make all login attempts unlimited.
- Expiry cleanup must coordinate with active operations. Bound attacker-created bucket growth and storage usage; do not evict active counters to regain space. Test a flood of unknown identities as well as a single username.
- Do not reset limits on deployment, PHP restart, parallel login, clock rollback or database restoration. Back up/restore state with a documented conservative policy. Account recovery must preserve generic responses to avoid enumeration.

**Preferred safety baseline:** retain SQL-backed `rate_limits`. It is already operational and avoids a new storage subsystem. A 13-table alternative below can do this without unrelated table merging.

**Conditional ten-table candidate:** durable private throttle files serialized by a database-server named lock for each namespaced bucket, using a dedicated/nonshared connection per concurrent request, rather than relying solely on `flock()`. Only a successful lock acquisition permits any bucket operation; release in `finally`, check errors, and never reacquire recursively by accident. Named locks are cooperative, connection-scoped and are not released by transaction COMMIT. They solve a proposed cross-thread serialization problem, not durable-storage or crash-consistency problems. This is a design inference requiring validation, not a tested fix. [MariaDB GET_LOCK documentation](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock).

An external atomic store could also keep the SQL count at ten, but would add an operational service absent from this deployment. It is not recommended merely to satisfy a table-count target. No such service is authorized or installed by this review.

Acceptance requires concurrency/fault tests under the actual Apache SAPI after approval. CLI PHP/browser happy-path tests alone cannot prove this replacement safe.

## 5. Nullable fields, JSON and responsibility boundaries

| Consolidation | Judgment | Required revision/limit |
|---|---|---|
| Users + one-to-one student profile + fixed role | Coherent; WARNING | Nullable student-only fields are justified. Enforce role-specific requirements; preserve canonical profile sources and ID mapping |
| Users + current OTP/reset/admin recovery state | High-complexity WARNING | Many nullable security fields and multiple lifecycles. Define separate field groups, generation/state invariants, constrained updates and a queryable history projection; never permit ordinary account forms to mass-update them |
| Departments + offices | Coherent organizational subtype; WARNING | Enforce kind-specific FK references. FMS department and Finance office must remain distinct identities |
| Cycles + period + fee rate | Coherent academic-cycle configuration; WARNING | Selected cycle and global unconfigured state need explicit representation; quote snapshots must not change with rates |
| Payment draft + application | Coherent lifecycle; WARNING | Preserve payment creation separately from clearance creation/start; draft rows must not leak into legacy clearance counts or document eligibility |
| Workflow templates + instances | Strongest relational-model concern; WARNING | Require total row-shape checks, typed self-FK, snapshot version and instance-only query contracts. Template rows are not work items and must never carry approval history |
| Evidence + receipts + certificate snapshots | Coherent document registry; WARNING | Explicit type-specific required fields, ownership constraints, two distinct verification contracts and immutable snapshot bytes |
| Notification + optional email job | Good one-to-zero/one consolidation; PASS conceptually | Explicit email-enabled flag/shape; read state and queue state independent; SENT remains SENT |
| Review history + payment history + audit + import/migration history | Coherent immutable events, but overloaded; WARNING | Explicit typed envelope, provenance and versioned payload contracts; no mutable settings, throttling buckets or current token secrets in the general ledger |
| Event ledger also used as control-number registry | WARNING | Global reference allocation is a mutable operational dependency on retained audit storage. Prefer nullable reference columns on existing payment owners plus a server-wide allocation lock and cross-owner lookup, or permanently retain typed issuance events outside ordinary archive eligibility. Choose one contract before implementation |

JSON is appropriate for immutable certificate snapshots, heterogeneous liability details and original legacy payload preservation. It is not an adequate substitute for indexed relational actor/request/stage/document IDs, statuses, amounts, timestamps, review-cycle numbers or report classifications. Do not require every report to deserialize all events.

Required scalar event columns include event family, event type, actor ID, application ID, stage ID, office ID or immutable office identity, cycle/version, occurred-at, source identity and stable within-source sequence. Approval/rejection outcome and amounts used in reporting should be typed fields, not substring searches over JSON.

Historical review cycles must remain reconstructible, including open cycles and response text. Imported snapshots of an open cycle do not later become editable log rows: later close/decision events extend the history. Define projection ordering by preserved source identity/sequence, not solely by timestamp; multiple actions can share one second.

Keep security history access separate from ordinary audits. If old credential-hash metadata is encrypted for retention, key custody, rotation, backup and restore must be specified. Do not introduce encryption that makes the only historical copy unrecoverable. Current recovery state stays in access-controlled scalar fields; no plaintext OTP/reset token goes in events.

**Maintainability conclusion:** the first design is a constrained compromise, not an equivalent fully normalized replacement. The most questionable savings are the recovery merge and the template/instance merge. Their implementation should be conditional on complete contracts; table count is not sufficient justification.

## 6. MariaDB constraints and migration hazards

MariaDB 10.4 can support the relevant InnoDB transactions, FKs, ordinary composite indexes, CHECK constraints, self-references and nullable unique indexes. This does **not** certify the unspecified target DDL. No tables were created to test it during this read-only review.

1. Use explicit `CONSTRAINT ... FOREIGN KEY (...) REFERENCES ...`, not only column-level `REFERENCES` shorthand. On older MariaDB versions such as 10.4 the shorthand can be accepted without creating the intended FK. Parent/child integer signedness, string types/collations and index definitions must match. [MariaDB foreign-key reference](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys).
2. Give named constraints database-wide distinct names for this version. Use physical discriminator columns for typed FKs and do not assume newer generated-column/FK features. [MariaDB constraint reference](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).
3. Nullable composite FKs can bypass matching when a participating component is NULL. Every INSTANCE must have non-null template/application/office discriminator fields; every receipt must have the required owner/version fields. Enforce these shapes explicitly.
4. UNIQUE nullable columns allow multiple NULLs. That suits optional `selected_slot` and template slots, but does not enforce “exactly one selected cycle,” “all templates are configured” or “all started students have the unique-start marker.” State transitions and total shape checks are still necessary. [MariaDB NULL reference](https://mariadb.com/docs/server/reference/data-types/null-values).
5. Avoid CHECK expressions that evaluate to unknown because a supposedly required field is NULL. Use explicit `IS NOT NULL` plus bounded comparisons. Cross-table eligibility and active-role checks need transactional validation, not a local row CHECK.
6. Do not reference an AUTO_INCREMENT primary key inside CHECK/default/generated expressions. Keep marker-equality checks on ordinary FK/value columns. Use tested RESTRICT actions for immutable historical links. [MariaDB AUTO_INCREMENT documentation](https://mariadb.com/docs/server/reference/data-types/auto_increment).
7. MariaDB JSON is text-backed. `JSON_VALID` checks syntax, not the business shape of an event. Use explicit payload versions and schema validation, reject duplicate object keys in newly generated events, and keep certificate JSON bytes unchanged. Avoid newer JSON query features not available on 10.4. [MariaDB JSON documentation](https://mariadb.com/docs/server/reference/data-types/string-data-types/json).
8. The inspected non-strict SQL mode raises a truncation/coercion risk. A future rehearsal/converter must set and verify a compatible strict session mode and treat all unexpected warnings as failures. Check enum conversions, numeric ranges, empty-string-to-NULL conversion and max identifier lengths explicitly. No live SQL mode was changed here.
9. FKs are immediate: there is no deferred-constraint loading assumption. Create/load accounts/cycles and documents/current-receipt pointers in a planned dependency order; permit only intentional temporary NULLs in the converter and validate before commit/cutover. Circular nullable links must not become permanent unverified pointers.
10. DDL is not undone by an ordinary transaction rollback. Preserve the original database and switch to a separately built target; never rely on wrapping DROP/ALTER in BEGIN. [MariaDB implicit commits](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

Major application hazards:

- New draft IDs can become the “latest clearance” and hide an existing completion. Rewrite every latest-request lookup with the intended started/policy semantics.
- Keep once-per-registration start behavior in addition to student/cycle uniqueness. The new payment draft model must not allow a new annual start unintentionally.
- Preserve stable source-ID lookup for evidence, receipts, recovery requests and old document URLs. A JSON mapping file alone is not a complete runtime lookup/authorization strategy; use typed legacy-ID columns/indexed provenance in the target model and verify ownership after resolution.
- Do not turn NULL/empty JSON into an empty valid approval. Keep legacy Finance no-control-number details readable without inventing payments.
- Old setup/startup/CLI migrations would target the original 30-table schema. Update and test all entry points together; a simple production DB switch is unsafe.
- Account/profile merge must retain authorization joins and private file routes; moving IDs must not change who can see a profile or receipt.
- Removing the certificate Action UI did not remove stored revocation state or the backend handler. Preserve existing validity behavior, but do not restore the removed UI button as part of this work.

## 7. Reports: exact preservation contracts

The actual PDF system has **one generator** in `reports/generate.php`, with weekly/monthly/custom date selection. Dashboards, clearance monitoring, officer workload and fee queues are additional operational reporting surfaces.

| Output | Existing definition | Target query contract |
|---|---|---|
| PDF total and status counts | One `clearance_requests` row; date of `COALESCE(started_at,created_at)` | Exclude new payment-only drafts using a durable clearance-created/started marker; retain legacy NOT_STARTED/CANCELLED rows even when start is NULL. Preserve the original clearance creation timestamp separately from earlier payment creation |
| Date windows | Inclusive calendar dates in application EAT; weekly last seven calendar days, monthly calendar month, custom bounds | Use equivalent `[from 00:00, day-after-to 00:00)` EAT boundaries if making predicates index-friendly; never silently use the user's PC timezone |
| Completed/in-progress/paused counts | Current request status, not number of events | Count application projection once; do not multiply it through joins to history/documents |
| Office approved/rejected/actions | `stage_actions` rows in range; counts repeated cycles; ACTIONS also includes PAYMENT_REQUESTED | Count only canonical stage-decision events, including PAYMENT_REQUESTED in total actions; exclude generic audit duplicates, payment-entry events, cycle events and imported row-snapshot events |
| Offices with no actions | LEFT JOIN retains all office rows, including inactive offices | Filter organization kind OFFICE, retain the same office set and zero rows; date/event predicates stay in join/subquery, not a WHERE that turns LEFT JOIN into INNER JOIN |
| Student list and current stage | One clearance row, first pending/rejected/in-review stage by step number | Instance-only projection. Under new policy distinguish awaiting corrections, locked Finance and completed; do not fall back to “Completed” solely because no actionable row was found |
| PDF certificate count | Legacy `certificates` rows by issued_at | Count LEGACY_CERTIFICATE documents by original issue time, not all documents or every certificate version. Existing 2 legacy + 2 current snapshots must not become a count of 4 |
| Admin certificate total | `transcripts` count | Count CLEARANCE_CERTIFICATE documents, retaining all existing versions/statuses as the current query does; do not count receipts/legacy certificates |
| Admin student/officer/office counts | Students, active OFFICER users, active offices | Student-role profile projection; same exact active role filter (not all staff); OFFICE units only |
| Recent clearances | Latest clearance IDs | Exclude drafts and templates, preserve original request identity/order and dates |
| Monitoring progress | Number of approved stages divided by 11 | Count distinct required INSTANCE steps, never template rows or repeated approval events |
| Officer “approved today” | Stage-action approvals by actor and EAT date | Canonical stage decisions only; exclude entry-payment approvals and same-action audit duplicates |
| Officer workload/returned filter | Assigned actionable stages and resubmissions | INSTANCE-only, assigned/authorized reviewer. Finance LOCKED is not actionable; new Finance REJECTED goes to awaiting-student, not the previous Finance special-case actionable queue |
| Finance fee queue | Entry payments scoped to assigned Finance; not-approved total includes awaiting evidence | Entry-payment projection including drafts; count once per student/cycle; retain existing scope and distinguish evidence waiting from verification waiting |
| Recovery request reports | OPEN/ALL/status filters, count, pagination and original request selection | Union/project current account recovery and historical recovery events without double-counting the latest request. Preserve original identity and deterministic order |

Freeze report semantics before refactoring. Preserve the source action ID as an ordering tie-breaker; an imported audit event's new ID is not a substitute for `MAX(stage_actions.id)` in certificate approval selection.

Two data semantics need deliberate handling: current profile/catalogue names are used by several live reports, while certificates use immutable snapshots. Keep the distinction. Historical reports should not unexpectedly change because a new implementation always displays snapshot names; equally, issued certificates must not refresh from a changed profile.

Validation must compare source and target rows/totals for all-time, empty ranges, exact start/end dates, month boundaries, repeated rejection/resubmission, payment-request actions, zero-action offices, revocation and multiple document versions. Compare PDF content excluding generation timestamp, then compare the rendered document. Isolate intentionally changed Finance statuses from schema-only equivalence so expected policy changes cannot hide counting bugs.

Until those comparisons pass, report accuracy is WARNING, not a promise that replacing table names is enough.

## 8. Data-loss assessment

The source data is available and internally consistent in the inspected respects. Nothing observed requires dropping an account, stage, payment or certificate. A lossless converter is **feasible in principle**, but cannot be confirmed without implementing and rehearsing it after approval.

Required reconciliation, beyond row counts:

- **56 accounts:** preserve every original field and hash byte, role, active/auth state and original user ID; prove the 43 student-ID-to-user-ID mappings are one-to-one. Hash-format recognition is not a substitute for comparing source/target hash bytes. Never export plaintext account passwords into the assessment or logs.
- **Applications/payments:** expect 9 application-lifecycle rows from 4 existing applications plus 5 unmatched payment drafts, conditional on unchanged source data. Compare amounts, cycles, timestamps, references, current receipt/version and approval actor/time individually.
- **Stages/cycles:** preserve all 44 current instances and 11 template definitions; all 46 review cycles and 33 stage actions retain source provenance, response, order, decisions and effective times. A rewritten current status cannot replace history.
- **History:** retain all 233 original audit rows and 26 payment-action rows even where one operation appears in both. Preserve their origin classification so reports count one canonical business decision rather than both records.
- **Files/documents:** six evidence files plus four issued-document records; byte/hash manifests, metadata, original URLs, public identifiers, snapshots, revocation and distinct verification behavior. Never recreate a new token for an old QR code.
- **Notifications:** 199 original inbox rows and 58 delivery states; SENT stays SENT. Import does not emit live SMTP.
- **Security/configuration:** preserve one OTP record and all supported future recovery behavior, six limiter buckets, nine migration markers, catalogs, 12 memberships and period/rate state. Empty tables still need tested features.

Every source record needs mapped destination/provenance and a comparison result. Source-to-target field checks, file checksums and exceptions are required; summing row counts is insufficient where merges/splits occur. Test a second import for idempotency. Encrypted archives count as retained history only if access, key recovery and historical feature queries still work.

Before cutover: verified full database/private-file/config/code backup, successful restore rehearsal, complete target reconciliation and tested switchback. After target writes begin, rollback requires reconciling/reversing those writes; switching back to an old snapshot alone would lose new approvals/payments. A previous UI test database is not a migration rehearsal.

## 9. Alternative ten-table designs

### A — Original proposal: 10 tables

`users`, `organisation_units`, `programmes`, `office_memberships`, `clearance_cycles`, `clearance_requests`, `clearance_stages`, `activity_log`, `documents`, `notifications`.

Advantages: preserves relational programme catalogue and many-office authority; keeps payments/stages/documents owned by real FK-linked entities. Risks: wide account/security rows, template/instance storage, event projections and unproven private-file throttling. **Original version: FAIL for implementation readiness.**

### A-R — Revised original: same 10 tables

Keep the same ten entities, but require:

- Complete scalar field/subtype contracts and tested FK/uniqueness constraints; business-state queries do not parse arbitrary JSON.
- Historical recovery request projections with stable identities, and separately modeled current OTP/reset/request states.
- Explicit canonical business-event classes and source sequence so audit consolidation cannot inflate reports.
- A selected control-reference ownership/retention strategy that cannot be broken by audit archival.
- Durable throttle storage with a proven cross-thread serialization mechanism; database named-lock coordination is a candidate requiring tests, not an already-approved implementation.
- Defined legacy/new Finance policy transition, global lock order and exact report contracts from this assessment.

**Recommendation within a mandatory ten-table limit: A-R is the least disruptive candidate to prototype after approval. Grade: WARNING.** It preserves the most current relational structure and avoids restricting existing features. Do not deploy until all failure gates are closed. If secure shared throttling cannot be demonstrated on this environment, A-R must be rejected rather than weakening authentication.

### B — Historical core-only design: 10 tables

`users`, `departments`, `programmes`, `offices`, `clearance_cycles`, `clearance_requests`, `clearance_stages`, `activity_log`, `documents`, `notifications`.

This gains separate departments/offices by removing the membership relation and putting a single office on the user; its historical version also retired recovery/import/legacy-document features. Those features are currently operational, unlike the intentionally removed escalation features. It also assumes an office corresponds to one workflow position. **FAIL: not equivalent to the current application. Do not reuse the rolled-back simplification.**

### C — Retain SQL limiter by moving programmes to configuration: 10 tables

`users`, `organisation_units`, `office_memberships`, `clearance_cycles`, `clearance_requests`, `clearance_stages`, `activity_log`, `documents`, `notifications`, `rate_limits`.

This preserves existing SQL throttling but removes the programme relation. A private mutable catalogue would have to replace programme administration/import validation, lose a true programme FK and introduce another backup/atomic-update responsibility. Static programme config would directly remove working administration behavior. **FAIL as the preferred preservation design; it moves the table-count problem into a less suitable storage layer.**

### D — Dedicated recovery and limiter, collapsed membership/catalogue storage: 10 tables

`users`, `organisation_catalog`, `clearance_cycles`, `clearance_requests`, `clearance_stages`, `activity_log`, `documents`, `notifications`, `security_recoveries`, `rate_limits`.

This puts departments, offices and programmes into a broad catalogue and pushes multi-office memberships into user JSON. It gives recovery its own table, but weakens the relational authorization path and mixes unlike catalogue entities to meet a numerical target. **FAIL: not a safer tradeoff.**

### Reference option when the limit can be reconsidered: 13 tables

Start with A, split `workflow_templates` from stage instances, split `security_recoveries` from users, and retain SQL `rate_limits`: **13 tables**. The recovery table represents the coherent recovery-request/challenge lifecycle with history preserved; it is not a generic cache/security bucket.

This is the safer maintainability baseline because it avoids the two hardest subtype merges and the new throttle backend. **It does not satisfy the ten-table requirement and is not proposed as a covert exception.** Reducing it back to ten without tradeoffs cannot be honestly guaranteed. No mathematically minimal table-count claim is made.

## 10. Approval and release gates

The first proposal is not ready for production implementation. The recommended direction under the hard limit is A-R, with approval permitting an isolated prototype/migration rehearsal first, not an immediate live cutover.

Required gates after approval:

1. Complete column-by-column mapping for all 30 sources, including historical identifiers and recovery features; freeze event/subtype/report contracts.
2. Execute and inspect the exact DDL on an isolated MariaDB 10.4.32 target; test invalid rows, partial-null FKs, role/type confusion, duplicate stages/references, strict-mode failures and installer table count.
3. Demonstrate throttle concurrency and fault behavior under the actual Apache SAPI; preserve current protections until replacement passes.
4. Implement/test payment rejection, payment gate, exact Finance prerequisite lock/unlock, final rejection/resubmission, stale versions, unauthorized actors and concurrent final approval.
5. Reconcile all accounts, history, files/documents, queues and reports against a restored source copy with external email disabled.
6. Rehearse backup/restore, idempotent conversion, pre-write switchback and handling of post-cutover writes. Obtain explicit approval for the audited transition of active Finance approvals.
7. Approve a separate production cutover once the prototype and reconciliation evidence are reviewable.

**Stop here. No implementation is authorized by this assessment request. The intentionally removed features remain removed.**
