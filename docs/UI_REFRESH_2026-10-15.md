# Interface and ODICT department update — 15 October 2026

## Interface

- The homepage and login use white surfaces and the OAS-inspired IRDP green/yellow palette. The homepage keeps one login action, with the entry card moved into view on phones.
- Public pages have an institutional masthead with Tanzania's coat of arms, Ministry of Finance / IRDP / motto and the IRDP logo. All authenticated views, including Admin, Student and Officer, omit this masthead and retain the profile/notification toolbar.
- The homepage and login both use the official IRDP green institutional banner matching the user's screenshot. On phones, its identity is rendered as scalable text with separate logos against a green patterned background; high contrast uses the readable text version as well. The banner URL includes its modification time to refresh cached artwork.
- The green footer appears on the homepage only. Login, recovery, verification and authenticated views omit it. Support contacts remain available through Need help everywhere.
- Authenticated navigation keeps the application frame in place and loads the selected PHP view in the main content area. Direct URLs, browser history, server authorization, CSRF validation, file uploads and normal document downloads remain supported.
- Notifications are available through the topbar bell. The student's duplicate Notifications/Updates dashboard cards were removed.
- The student's name and picture appear in the account menu at the top right. The dashboard no longer repeats the welcome/name/picture block.
- Logout appears only in that account menu.
- Sidebar links contain role-appropriate services only; Admin links are grouped under Clearance and Administration. Change Password and Log out are in the top-right account menu for every role. Students also access My Profile there.
- The student dashboard combines its progress circle and all eleven offices in one overview. Desktop has a narrow progress summary beside two columns of compact office rows; phones stack them with readable status labels. Duplicate status panels and expanded correction/payment forms no longer consume the overview.
- My Clearance uses the same numbered office rows and status icons. Office details, review history, Finance receipt forms and corrective evidence expand on demand. Dashboard links and direct URLs with an office fragment open and focus that office automatically; native keyboard controls work as well.
- Shared headings, panels, form labels, field-to-button gaps and column grids now use consistent spacing. Notifications have separate controls, All/Unread filters, chronological date groups, message icons, unread markers and readable timestamps. Mark all as read retains its existing user-scoped, CSRF-protected behavior.
- Officer pages show the signed-in officer's name in the toolbar and dashboard heading. Icon summaries show pending reviews, returned evidence, today's approvals and outstanding student corrections. The first two summaries filter the assigned work list; Finance has a compact payment-management link.
- My Profile groups the student identity and academic details beside picture/email settings, using local camera, mail, phone and academic icons. Students can save their own phone number; validation accepts national/international contact formats and stores a normalized international number in the student and user records within one audited transaction.
- Accessibility uses a green/yellow button matching Need help. Its scrollable panel retains only text size, high contrast and reset, with a close button, Escape support and saved preferences. On phones the trigger reduces to its icon; the panel stays within narrow and landscape viewports.

## Shared Need help panel

The green/yellow headset button opens an accessible, non-modal help panel on the homepage, login, recovery screens, verification pages and authenticated views. It uses the two existing support numbers; it does not introduce new contact details or an external chat service.

- **Mwongozo** displays short instructions for the current page and role. It follows navigation inside the persistent application frame.
- **Maswali** retains the ten existing detailed FAQs, adds local search and announces matching/no-match results.
- **Mawasiliano** provides call links for `0659913570` and `0662632565`.
- Keyboard controls include labelled tabs, arrow-key navigation, Escape and focus return. Opening help preserves unsaved form input.
- The panel scrolls within the viewport, including short landscape screens and 200% text. Accessibility controls occupy the opposite bottom corner. Reduced-motion, contrast and print preferences are respected.

## Verification

- `php tests/clearance_once_test.php`: passed real HTTP checks with two independent sessions submitting concurrently (one request and eleven stages), plus attempts across a new academic cycle in IN_PROGRESS, PAUSED, COMPLETED and CANCELLED states. Direct start/fee POSTs and the fee service cannot create another request/payment. Phone checks passed for normalized saves, synchronized account/student records, forged student IDs, invalid numbers, missing CSRF and staff-role rejection. The test database, server processes and receipt fixture are removed.

- `php tests/responsive_test.php`: passed **748 browser layouts** at 320–2560px, including 100%/200% text, 844×390 landscape and **135 scrollable-table checks**. Help and Accessibility additionally passed narrow-screen, short-landscape and 200% text checks. Accessibility preference persistence, contrast, reset, close/Escape focus and floating-button spacing passed.
- Browser interaction checks passed for the shared contextual Help panel, FAQ search/no-match feedback, original contacts, keyboard/focus, preserved form input, persistent navigation, active menus, form submission/redirect, validation after navigation, Back/Forward, profile preview, unread counts and correction anchors.
- The browser suite also checks that authenticated pages omit the institutional masthead, public pages have one header with working logos, only the homepage has a footer, and notification filtering/mark-all update both cards and the topbar unread count. Officer name display, assigned-work summary counts, evidence/all filters, profile phone saves and automatically expanded office evidence forms passed.
- Session browser checks passed for warnings, continuation, failed continuation, idle expiry and absolute expiry.
- `validation_test.php`, `password_accessibility_test.php`, `session_security_test.php` and `browser_validation_test.js` passed.
- Changed application PHP files and JavaScript syntax checked successfully.
- Browser and session suites used disposable databases; both runners removed their databases on completion.

### Institutional artwork source

`assets/img/tanzania-emblem.png` was downloaded unchanged from the [official IRDP OAS asset](https://oas.irdp.ac.tz/images/tanzania-emblem.png) on 15 October 2026. The header follows the [OAS masthead](https://oas.irdp.ac.tz/) structure and reuses the existing local IRDP logo. Both emblems are served locally. Public footer service links are published on the [official IRDP website](https://www.irdp.ac.tz/); clearance support numbers retain the project's existing values.

`assets/img/irdp-institution-banner.jpg` is the unchanged [official IRDP header banner](https://www.irdp.ac.tz/FrontEnd/extra-images/logo/banner-1.jpg), used on the desktop homepage to match the user's reference. `institution-pattern.svg` is a local geometric motif for the responsive text version.

## Live ODICT department correction

All 11 existing ODICT students in `irdp_e_clearance` were moved to the newly created active FMS department, **Department of Finance and Management Studies**. Both `students.department_id` and `users.department_id` were updated in one transaction and verified before commit. BTCRP and BTCCD records were outside the update.

Original department references were saved in `storage/private/odict-departments-20261015-163254-c9d957f6.json`; an `ODICT_DEPARTMENT_UPDATED` audit entry records the affected student IDs.

`jobs/move_odict_department.php` is CLI-only. Without arguments it inspects the database; `--apply` performs the verified move and makes no changes when all ODICT students already belong to FMS. Existing demo profiles are preserved by the current seed routine, so this change does not require changing demo credentials or the seed definitions.

Following the department correction, the user authorized assigning its Head of Department. The active officer account `FMS001` (display name `Head of Department FMS`) now belongs to FMS and the Head of Department office. Its generated initial password is held as a hash; login requests a password change. Existing EPM staff authority is preserved.

Two unfinished departmental stages were reassigned to FMS001; their original assignment references were backed up in `storage/private/fms-review-assignments-20261015-163611-352767f9.json`. Decisions and original officer history were preserved. `FMS_HEAD_ASSIGNED` records this operation in the audit log. `jobs/assign_fms_head.php` provides CLI-only inspection and an explicit `--apply` option.

## Student email recovery and final verification — 16 October 2026

Student Forgot Password now uses the email saved on the account. Send OTP opens a same-browser verification step; a six-digit code expires after 10 minutes and allows five attempts. Resend becomes available after 60 seconds and invalidates the old code. Successful verification grants 10 minutes to choose a new password. Password changes revoke existing sessions and require a fresh login. Unknown, inactive and staff addresses receive the same public response. Support retains administrator-assisted recovery after identity verification for staff and students without email access.

The additive `2026_runtime_v15_student_email_otp` upgrade adds `student_password_otps`, bringing the schema to 30 tables. SMTP sends recovery codes directly; only hashes enter the database, and plaintext codes never enter the notification outbox or audit history. Help, FAQs and the operating documentation now describe this flow.

Verification before commit on 16 October:

- `student_password_otp_test.php` passed migration preservation, hashed/one-use codes, expiry, attempts, resend, email/password changes, throttling, delivery failure, local SMTP and the complete HTTP reset with browser binding and CSRF.
- `features_test.php` passed all checks across 298 HTTP requests, including office authorization, Finance payments, evidence, transcripts, imports, administrator-assisted recovery and repeatable migrations.
- `clearance_once_test.php` passed simultaneous starts, all existing clearance states across new cycles, direct POST guards and student phone authorization/validation.
- `responsive_test.php` passed 805 layouts at 320–2560px, 100%/200% text and landscape, plus 135 scrollable-table checks and navigation, Help, accessibility and session interactions.
- Email notifications, all fifteen isolated demo students, validation, password accessibility, session security and browser validation passed. Changed PHP, JavaScript and PowerShell files passed syntax checks.

Database and SMTP tests used disposable fixtures; their runners removed the test databases. The browser suite required execution outside the workspace sandbox after its first attempt timed out while enabling Edge. No live department or account changes were repeated during this verification.
