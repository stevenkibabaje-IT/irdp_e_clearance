# IRDP A-R Prototype Supervisor Demo Checklist

> Historical checklist for the 18 October CLI prototype. For today's browser demonstration and current test results, use [Assessment readiness — 19 October](../docs/ASSESSMENT_READINESS_2026-10-19.md). Its isolated URL is `/irdp_e_clearance/migration_ar/`; the older limitations below describe the earlier prototype.

## Before the demo

- [ ] Confirm the target database is `irdp_ar_proto_20261018`; do not use or connect an application account to the production database.
- [ ] Confirm SMTP remains disabled (`mail_enabled: false`). Do not enable real email or notification delivery.
- [ ] Review `storage/private/ar-prototype/results/latest.json` (run `81759e80189f1e80`). It records **31 passed, 2 failed**; both failures are the pre/post source-snapshot equality guards.
- [ ] Explain the source drift: no baseline rows were removed or changed. New rows were appended to `audit_logs` (+7), `clearance_fee_actions` (+2), `clearance_fee_payments` (+1), `email_outbox` (+1), and `notifications` (+1). Their DDL differs only by advanced `AUTO_INCREMENT` counters. One `rate_limits` row changed `window_start` and `attempts` (1 to 2), consistent with a later rate-limit attempt.
- [ ] Explain the integrity result: the read-only source check found 46 foreign-key constraints and zero orphans. The differences are additive operational history plus rate-limit state, not evidence of broken relationships; they still mean the prototype snapshot is not an exact copy of the current source.
- [ ] The target prototype remains isolated with 10 InnoDB tables, 35 foreign-key constraints, 29 CHECK constraints, and 830 archived source rows.
- [ ] Do not rerun the full suite casually: it appends labeled test users, cycles, requests, stages, documents, audit events, and notifications to the isolated target. Do not delete demo/test records.

## Demonstration sequence

- [ ] Show that all 56 documented account credentials authenticate against the prototype; show a rejected password and unknown username. Never display or log passwords.
- [ ] Show a student's payment request and receipt submission. Verify that clearance start is denied before Finance approves the current receipt version.
- [ ] Reject a receipt with a reason, submit a replacement, and show that the stale receipt version cannot be approved.
- [ ] Approve the current receipt as its assigned Finance officer, then start clearance. Show exactly 11 stages: 10 offices and final Finance.
- [ ] Attempt final Finance approval while the stage is locked; confirm denial.
- [ ] Reject and resubmit one office stage, preserving its review history. Approve all 10 office stages and show that final Finance unlocks exactly once.
- [ ] Confirm an office officer cannot approve the final Finance stage. Complete it with the assigned Finance officer and show certificate issuance.
- [ ] Show the persisted workflow fixture as test evidence: payment approved, 10 office approvals, final Finance approved by a Finance member, exactly one Finance unlock, and both certificate tokens verify.
- [ ] Be explicit about QR status: the clearance-certificate PDF builder has no QR code. The QR renderer is used by the transcript PDF path; its matrix was generated, but no QR scan was performed. A clearance-certificate QR cannot be demonstrated until prototype-only PDF output includes one and a real scan test passes.
- [ ] Review report-equivalence checks, foreign-key/orphan checks, authentication/rate-limit checks, and the disabled-notification state.

## Guardrails and outcome

- [ ] Keep the demo on the isolated prototype only. Do not deploy, modify production data/files, or send real notifications.
- [ ] Do not restore escalation, delegation, or appeals. Their absence is intentional.
- [ ] Do not run cleanup, reset, drop, or destructive migration commands. Ask for approval before any destructive action.
- [ ] State the integration boundary: workflow services are CLI-test oriented, and the only HTTP route is a localhost-and-capability-protected test probe. Student/officer browser workflows, application authentication/CSRF integration, and certificate-PDF QR delivery are not integrated here.
- [ ] Present this as a database/service prototype demonstration, not an end-user application or production approval. Production readiness is **not established**: current-source reconciliation needs an approved non-destructive baseline decision, and browser integration plus certificate QR scan verification remain outstanding.
