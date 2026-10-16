# Full system test report — 13 October 2026

## Result

The test suites listed below passed after two application bugs were corrected.
The live database still has 29 tables and 33 active demo accounts: 21 students,
11 officers and one administrator. No live clearance, payment, profile or
password records were changed by the tests. Live login checks create normal
audit/rate-limit records. Destructive workflows used disposable databases.

## Bugs found and fixed

1. **Repeated setup overwrote student profile corrections.**
   `seed_demo_accounts()` unconditionally rewrote the programme, academic year,
   registration number and department of existing seeded students. A regression
   test changed a student's programme/year and reproduced the data overwrite.
   Existing profiles are now preserved; only the explicit legacy-registration
   conversion updates them. Both repeated seeding and forced setup passed the
   new preservation check after the fix.

2. **SMTP command timeout was assigned to the wrong object.**
   The application assigned `Timelimit` to PHPMailer, where that property does
   not exist. PHP 8.2 emitted a dynamic-property deprecation and the intended SMTP
   command time limit was not applied. The assignment now uses PHPMailer's SMTP
   instance. Local SMTP delivery/retries passed without PHP warnings or
   deprecations; the test now checks for these explicitly.

Two test-harness defects were also corrected: the older account test still
expected five seeded students rather than fifteen, and the documented-account
smoke test attempted GET logout although the application correctly requires
CSRF-protected POST. The smoke test now confirms access is denied after logout.

## Verification

| Test | Result and coverage |
| --- | --- |
| PHP syntax | PASS: 105 application/test PHP files; all six changed PHP files rechecked |
| `validation_test.php` | PASS: names, dates, money, IDs, nested/hostile inputs, uploads, Finance controls/receipts |
| `password_accessibility_test.php` | PASS: password boundaries, Unicode, legacy hashes and accessible errors |
| `spreadsheet_xml_test.php` | PASS: valid/prefixed/empty XML, unsafe/malformed/oversized input, duplicate cells and invalid worksheet positions |
| `demo_students_test.php` | PASS: 15 seeded students, login, optional password changes, duplicate prevention, history preservation and new profile-preservation regression |
| `parallel_clearance_test.php` | PASS: migration preserves assignments, approvals, corrections, evidence and issued documents |
| `finance_payment_migration_test.php` | PASS: legacy payment conversion, history preservation and repeatability |
| `session_security_test.php` | PASS: idle/absolute expiry, revoked sessions, expired POSTs, CSRF renewal, cookie settings and logout |
| `email_notifications_test.php` | PASS: real PHPMailer delivery to a local SMTP fixture, Unicode, queue deduplication, retries, cancellation and stage-specific notifications; warning-free after fix |
| `features_test.php` | PASS: 309 HTTP requests; independent approvals across all 11 offices, rejection/resubmission, Finance fees/debts, private receipts, completion, PDFs, immutable/revoked transcripts, imports, recovery and permissions |
| `responsive_test.php` | PASS: 507 layouts from 320–1440px, 100%/200% text and 113 scrollable-table checks; navigation, focus and session warning/continuation |
| `browser_validation_test.js` | PASS: role/department rules, money, files, report/period dates and Unicode confirmation |
| `profile_image_test.ps1` | PASS: portrait, landscape and small-image resize dimensions/content |
| `live_demo_accounts_test.php` | PASS: all 15 seeded logins and profile isolation, using an isolated database through `DB_NAME` |
| `documented_demo_accounts_test.php` | PASS: all 33 published live credential hashes and 29 tables; real Apache login/dashboard/logout for Student, Admin and Finance |
| Apache public/private routes | PASS: landing/login HTTP 200; storage, config, includes, jobs and tests HTTP 403 |

The first browser run timed out inside the sandbox before it could enable page
inspection. The same suite passed outside the sandbox. This was an execution
environment limitation, not a failing page assertion. All disposable test
databases were removed by their runners.

## Scope limits

Email was tested against a local SMTP fixture; delivery to Gmail or another
external provider was not attempted. Browser checks used installed Edge on this
Windows/XAMPP machine. Other browsers, real mobile devices, screen-reader use
and production load were not assessed. Passing these tests does not establish
that every possible defect is absent.

## Reproduction

Use PHP with PDO MySQL, mbstring, fileinfo, curl and GD, plus Node 22+ and
Edge/Chromium for browser checks. The relocated PHP executable on this machine
is `C:/xampp1/php/windowsXamppPhp/php.exe`; child PHP processes need the same
extension configuration. Tests accept the local server DSN:
`mysql:host=127.0.0.1;port=3306;charset=utf8mb4`.

Initial and focused rerun logs are stored privately in
`storage/private/system-test-20261013/`. The initial responsive log records the
sandbox timeout; the successful rerun result is recorded above.
