# Feature operations and verification

## Installation and additive migration

Back up the existing database before installing. Configure DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS in the environment, or use config/database.php. The database name is irdp_e_clearance. Credentials must belong to an account permitted to connect from the configured host. A database name alone does not resolve an account permission error.

Run jobs/migrate.php with the XAMPP PHP executable from the project directory. Application startup also applies the same repeatable migration. includes/migrations.php records version 2026_features_v1, adds feature tables and columns, preserves existing accounts, decisions and requests,. database.sql no longer drops existing tables.

On 1 October 2026, the configured live root connection succeeded at 127.0.0.1:3306. A backup was saved under storage/private/backups before jobs/migrate.php completed successfully. The live database records migration 2026_features_v1. No database grants were changed.

## Dependencies and configuration

Use PHP 8.2 with PDO MySQL, fileinfo, DOM/XML, Phar and sessions, plus MariaDB/MySQL. No Composer package is required. Profile resizing uses GD when available; on Windows it can use PowerShell and System.Drawing through includes/resize_profile.ps1. PHP must be allowed to launch that helper when GD is absent.

For multiple evidence attachments, configure upload_max_filesize to at least 5M and post_max_size to at least 32M. Keep storage/private inaccessible directly through the web server. storage/.htaccess denies Apache access; configure an equivalent deny rule on other servers and ensure Apache honors the file. Files are served through authenticated authorization checks. tests must also remain inaccessible over the web.

config/application.php contains the 8-64 character limits for newly entered passwords, upload/import limits, private storage location, mail flag and application origin. Existing longer password hashes still work at login. Use HTTPS and an HTTPS application origin outside local development.

## Office review workflow

Emergency Review, Continuity, Escalations and Appeals, and Settings and Authority were removed on 7 October 2026. Their former URLs redirect authenticated staff to the normal dashboard and do not process actions. The former deadline job exits without accessing the database, so an old scheduler entry cannot generate escalations.

New requests require active office reviewers for all eleven stages. Stage 7 also requires the reviewer's matching department. Review permissions continue to be enforced for every decision and evidence download. Existing Supervisor accounts use the ordinary officer dashboard and can review only their assigned stages with office access.

All eleven offices start as PENDING and can review in any order. A rejection affects only that office; PAUSED indicates student action while other officers continue reviewing. Resubmission updates only the rejected office and retains PAUSED if another rejection remains. Finance can request payment and approve a valid receipt before other offices finish. Completion and document issuance occur after the last outstanding office approves, regardless of its stage number, with all eleven approvals and liability checks still required.

The automatic 2026_parallel_clearance_v1 upgrade opens LOCKED stages on IN_PROGRESS/PAUSED requests, initializes their review cycles and preserves decisions, evidence, assignments and issued documents. Completed and cancelled requests remain unchanged. It runs once under the installation lock; normal requests continue to bypass migrations. Run tests/parallel_clearance_test.php against an isolated database to verify upgrade preservation and repeatability.

Historical database tables and records are retained for compatibility with existing installations. There is no automatic availability/delegation routing or escalation processing.

## Finance payment review

Finance (stage 11) uses a control number and the **Reject** / **Approve** buttons. Enter a 6–30 digit control number and choose Reject to send the payment request to the student. The student sees it on the dashboard and My Clearance, pays, and uploads one PDF/JPG/PNG receipt up to 5 MB. Finance can then open the private receipt and approve or reject it. Approval is blocked until a receipt for the current control number and review cycle exists. Changing a control number requires Reject and a new receipt. Rejected receipts and previous control numbers stay in the history. Payment verification is performed by Finance; uploading a receipt alone does not complete clearance. Existing approved records and issued clearance documents remain usable.

## Accounts and recovery

Accessibility controls on the landing page, login and shared pages offer text sizes from 100% to 200%, high contrast and reset, saved locally in the browser. Skip links, visible focus, mobile navigation state/close/Escape, keyboard-scrollable tables, linked field errors/help, screen-reader clearance progress and reduced-motion support are included. Browser verification covered login at 320 pixels with 200% text, contrast, focus and preference persistence. A full WCAG conformance audit and manual screen-reader testing have not been completed.

Authenticated users change their password by supplying their current password and confirming the new password. Students open their dashboard immediately after login and may choose Change Password from My Profile. Legacy forced-change flags do not restrict student features; staff requirements are preserved. Newly entered passwords must contain 8-64 characters; spaces and Unicode are allowed and values are never truncated. A local common-password blocklist, predictable service-password checks and repeated-pattern checks reject weak choices. Argon2id stores new passwords without bcrypt truncation; PHP Argon2id support is required. Existing bcrypt hashes remain valid at login. Changing to the current password is rejected. Password changes and resets invalidate previous sessions through auth_version.

Every signed-in role has a Log out button in the shared top bar and sidebar. On narrow phones, use the logout button in the navigation drawer. Logout uses a CSRF-protected POST request, destroys the current session and returns to the login page.

Forgot Password records a password reset request for an active account even when email is disabled or unverified. Responses remain generic and rate limited; repeated submissions keep one open request per account without replacing an issued code. Active administrators receive an internal notification, a sidebar count and a dashboard link to Password Reset Requests. The queue supports open, pending, issued, completed, rejected and all views with pagination. Automatic mail recovery requires functioning mail transport, PASSWORD_MAIL_ENABLED enabled, an appropriate MAIL_FROM and APPLICATION_ORIGIN, and a verified account email. Failed mail delivery leaves the request pending for administrator review. Mail delivery is disabled by default and has not been tested against an external provider.

An administrator opens Password Reset Requests, selects Review request, verifies institutional identity, records the verification reference and chooses Approve and issue reset code. Deliver the displayed code privately to the verified account holder. The user opens Reset Password, enters the code and confirms a new password; the request becomes Completed and existing sessions are revoked. Administrators can issue replacements for lost/expired codes or reject requests with a recorded reason (revoking their issued code). Manual admin-assisted recovery remains available and is also tracked. Codes expire after 30 minutes, are stored only as hashes, and are invalidated after use or replacement. An authenticated password change also closes outstanding requests. Admin recovery and reset responses use no-store caching. The additive migration runs once on the next request and leaves existing accounts and clearance data intact.

## Rejection and evidence

Reviewers must provide both a rejection reason and corrective instructions. Students open the rejected stage from their status page, enter a response and optionally attach up to five PDF/JPG/PNG files, each at most 5MB. Files undergo MIME/content checks, receive generated private names and remain linked to the relevant review cycle. Authorized access is limited to the Student and assigned reviewers with matching office access.

Resubmission creates a new review cycle only for that stage. Existing approved stages stay approved and other pending offices can continue reviewing. Prior comments, decisions, responses and evidence remain visible in history. Students may submit further corrections after a rejection without an appeal. Every correction requires a new office decision. Final documents require all eleven approvals and cleared liabilities.

The authorized officer verifies the current submission and uses **Approve evidence and continue** for attached files, or **Approve correction and continue** for an explanation verified with the office. This explicit confirmation resolves previous amounts, missing items and the office decision; it preserves the original rejection findings. A response alone never approves a stage. Confirmation rejects stale review cycles, unauthorized officers and submissions with neither a current response nor files.

## Profile pictures

Students upload their own JPG/PNG image up to 5MB. The application decodes the image, checks dimensions, and resizes it into a 256-pixel square while preserving its proportions. Replacement/removal cleans up unreferenced files. Access is private; an absent picture uses the default avatar.

## Future Development — Student Bulk Import

The current presentation workflow uses five seeded demo students. Prototype CSV/XLSX import utilities remain available in the codebase for future development and automated regression testing, but bulk import is excluded from the current demo navigation. A production version should accept authorized institutional records, validate registration numbers and programme references, reject duplicates, and securely generate initial credentials.

Preview validates every row and identifies duplicates within the file and existing records. XLSX parsing reads the first worksheet and rejects macro/binary content, formula cells, unsafe XML and archive expansion beyond bounds. Commit requires a current session preview and revalidates rows. Only valid rows are created; created/skipped/failed totals are reported and audited. Each imported account receives a unique inaccessible random initial secret and requires individual verified activation/reset. There is no shared student password.

The XML reader accepts both default and prefixed namespaces, including valid empty shared-string tables. It checks SimpleXML's strict false return value rather than treating valid empty/prefixed XML objects as parsing errors. Run `php tests/spreadsheet_xml_test.php` for these regressions and malformed/unsafe XML rejection. The feature suite uploads, previews and confirms an XLSX import of 50 fictional students with a prefixed worksheet and empty shared-string table in its isolated database.

## Verification

Run `php tests/features_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"` from the project directory. The suite creates a randomly named isolated database and removes it afterward. Optional credentials use IRDP_TEST_USER and IRDP_TEST_PASSWORD. The test account requires CREATE/DROP DATABASE privileges. PHP CLI, curl support and a free local port 18087 are required.

Coverage includes active office assignment, legacy availability records being ignored, blocked unauthorized/inactive reviewers, repeated student corrections without appeals, rejection history, evidence downloads, all eleven independent approvals, early Finance review, multiple rejected offices, final non-Finance completion and certificate checks, concurrent decisions, private profile images, passwords and account recovery, import validation, transcript generation without any results tables, clearance-only PDF content, stable downloads, transcript verification and revocation, and migration preservation. HTTP checks also verify that removed feature URLs accept no former actions and that admin/reviewer menus do not expose them.

Run tests/validation_test.php, tests/password_accessibility_test.php and tests/spreadsheet_xml_test.php for focused checks. Run `node tests/browser_validation_test.js` for the JavaScript form-control harness. See [Input validation audit](INPUT_VALIDATION_AUDIT.md) for the accepted rules, fixes and test coverage.

## Clearance period and clearance transcripts

View Certificate has been removed from the student Dashboard and My Clearance. The former certificates/certificate.php URL redirects authenticated students and administrators to their dashboard without rendering or issuing a certificate. Landing-page guidance and new completion notifications point to the Clearance Transcript. Historical certificate records, automatic issuance, PDF download authorization and public verification are retained for compatibility.

The Admin Clearance Period Management page controls a single active period. It supports manual OPEN/CLOSE and scheduled opening/closing timestamps tied to an academic cycle. Student login remains available while the period is closed, while student/start.php and student/resubmit.php reject POST operations server-side with the official closed-period message. Existing officer review and administrative operations remain available.

Academic Results has been removed. The former Admin and Student URLs redirect to the corresponding dashboard and do not display or edit results. The academic results provider and module/grading/results database setup have been removed. Existing legacy tables are left untouched for data preservation and are never accessed by the application; fresh installations do not create them.

includes/transcripts.php creates a clearance transcript after all eleven stages are approved and all liabilities are cleared. Each office entry comes from its latest recorded approval action and includes the office, the actual approving officer and the approval timestamp. The immutable snapshot contains student details, the clearance academic cycle, start/completion timestamps and the office approvals. It contains no academic results, marks, grades or GPA.

Completed clearance requests receive one immutable clearance transcript per request. New cycles receive new document versions; repeated downloads reuse the issued snapshot. A valid document from the previous academic format is replaced on download by a new clearance version, and its old token is revoked; its historical snapshot is preserved. Revoked documents are not silently reissued. The PDF includes student/programme details, the office approval table, a unique document number and a secure random verification token rendered as a scannable QR matrix. transcripts/verify.php reads the issued snapshot for verification details. Admin can revoke a document with a reason and verification then reports DOCUMENT REVOKED. The QR encoder is the LGPL TCPDF QR implementation kept in includes/qrcode.php.

Live database migration was verified on 1 October 2026 after taking a backup. External mail delivery remains unverified until the transport is configured.
