# IRDP Student Clearance System

A local PHP/MySQL/XAMPP implementation of the IRDP student clearance workflow.

Contributions are welcome through forks and pull requests at [stevenkibabaje-IT/irdp_e_clearance](https://github.com/stevenkibabaje-IT/irdp_e_clearance). See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, checks and submission instructions.

The theme uses IRDP green `#60BA6D`, dark-green accents `#075D39`, deep-green headings `#06452C`, a light background and white surfaces. Roboto is hosted locally under `assets/fonts`, with its included SIL Open Font License. Login, landing page and shared dashboards use the same green theme; light-green buttons use dark text for contrast.

The web logo is the official PNG from [IRDP's Online Application System](https://oas.irdp.ac.tz/images/logo-sm.png), stored locally as `assets/img/irdp-logo-web.png`. Landing, login and dashboard navigation show the full emblem on a white surface without cropping the motto. The login card also shows the logo on phones. The original JPEG is retained for PDF documents.

The home page uses a compact introduction, a short feature row and a small footer with click-to-call support at `0659913570` and `0662632565`. Help / Msaada stays collapsed until opened, with Swahili guidance for login, password recovery, clearance progress, resubmissions and clearance transcripts. The page grows naturally on small screens or with enlarged text.

The layout adapts to phones, tablets and desktops. At widths up to 1024px, navigation opens as a drawer with a backdrop, Escape-to-close and keyboard focus containment. Dashboard cards and forms stack as space decreases; wide tables scroll within their own labelled regions.

**View Certificate** has been removed from the student Dashboard and My Clearance. Its former URL returns authenticated students and administrators to their dashboard. Completed students can still download their Clearance Transcript. Historical certificate records, automatic issuance, PDF downloads and verification are retained for compatibility.

Select the **accessibility icon** at the bottom right of the landing page, login page or inside the system to enlarge text up to 200%, enable high contrast, or reset the display. Preferences are saved in this browser. The login password field uses an eye icon to show or hide the password; icon controls retain screen-reader labels and keyboard access. Keyboard users can use the skip link, Tab/Enter, and Escape to close mobile navigation. Form labels, help and errors are linked for screen readers; reduced-motion preferences are respected.

New passwords must contain **8–64 characters**, including passwords longer than 15 characters. Spaces and Unicode are supported; common passwords, simple repeats and predictable service passwords are rejected using a local blocklist. Existing passwords still work at login. Students go directly to their dashboard even when a legacy forced-change flag is set. **My Profile → Change Password** is optional and requires the current password. Staff first-login requirements are preserved. Newly created accounts, password changes and resets use Argon2id (PHP Argon2id support required). Password changes revoke existing sessions. Run `php tests/password_accessibility_test.php` for focused checks.

The administration menu includes Dashboard, Users, Password Reset Requests, Programmes, Clearance Period, Clearance Transcripts, Offices, Departments, Workflow, Clearance Monitoring and Reports. Emergency Review, Continuity, Escalations and Appeals, and Settings and Authority have been removed. Older URLs return staff to their normal dashboard without processing actions.

Clearance stages are assigned to active reviewers belonging to the stage's office (and the student's department for stage 7). The Users form assigns an office when creating a reviewer. Student corrections and evidence can be resubmitted after rejection without an appeal; each submission still needs office approval.

Normal requests check the database version and skip installation, migrations and demo seeding once setup is complete. A new schema version triggers one setup under the database lock. For an explicit repair/setup check, use `setup.php` or `php jobs/migrate.php`. Page and officer-queue indexes are installed during setup. CSS/images use browser caching, text responses use compression when Apache supports it, and profile images revalidate privately after authentication.

Measure local response times with `php tests/loading_probe.php`; an optional first argument specifies the page URL. Run `tests/features_test.php` against the isolated test database to verify normal startup bypasses the migration lock and clearance/upload workflows still work.

When an office rejects a stage, the student dashboard shows **Upload evidence and resubmit**. In **My Clearance**, each rejected stage displays the reason, corrective instructions, an explanation box and file upload. Click **Submit evidence to office** to send the response and up to five PDF/JPG/PNG files (5 MB each). The responsible office sees the resubmission and documents in its review page. Other offices continue reviewing independently; existing approvals and the evidence history are preserved.

For a resubmission with files, the officer uses **Approve evidence and continue** after verifying that the evidence resolves all office requirements. For a response without attached files, **Approve correction and continue** allows the officer to confirm corrections verified with the office. Both record remaining amounts as cleared, missing items as available, and the office decision as cleared. Earlier rejection details remain in the review history. Submitting a response alone does not approve the stage; the authorized officer must verify and approve it. Ordinary approval still requires the office fields to show cleared requirements.

Profile pictures: open **My Profile**, choose a JPG or PNG up to 5 MB, review the preview, and click **Save picture**. The saved portrait appears on the profile, dashboard and account header. XAMPP upload limits are configured in `.htaccess`; other PHP servers need `upload_max_filesize` of at least 6M and `post_max_size` of at least 32M.

On Windows, verify that resizing preserves the actual image content with `powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/profile_image_test.ps1`. This checks large portrait/landscape images and small images, including their colours. Pictures saved as blank images by the earlier resizing bug must be uploaded again from the original file.

The landing and login background uses a 2048 × 1536 campus photograph from [this IRDP campus article](https://www.universityscoop.com/institute-of-rural-development-planning/), stored locally as `assets/img/irdp-campus-hd.jpg`.

See [feature operations and verification](docs/FEATURES.md) for clearance, recovery, evidence, profile and import operations, migration instructions, dependencies and verification. Run tests/features_test.php against an isolated test server before deployment.

Forgot password: users submit their username / registration number from the login page's Forgot Password link. Admins see the request under **Password Reset Requests** (also linked on their dashboard), verify identity, then approve and issue a reset code. The user enters that code on **Reset Password** and chooses a new password. Requests are marked Completed automatically; rejected requests and expired/replacement codes remain visible in the admin history. This works with email disabled.


## Normal installation

1. Extract the project into `C:\xampp\htdocs\irdp_e_clearance`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/irdp_e_clearance/`.
4. The application automatically creates the database, required tables, workflow reference data and missing demo accounts.
5. Login normally. **You do not need to open `setup.php` first.**

`setup.php` is retained only as a manual diagnostics/re-initialization page; it is not part of the normal login flow.

After login, Students, Officers, Supervisors and Administrators can use **Log out** in the top bar or sidebar. The top-bar button is also available on mobile and returns users to the login page after ending their session.

## Demo accounts

### Administrator
- Username: `admin`
- Password: `Admin@IRDP2026`

### Officers
- `LIB001` / `Mrema@2026`
- `SPORT001` / `John@2026`
- `COMP001` / `Massawe@2026`
- `SUP001` / `Mallya@2026`
- `TRANS001` / `Kweka@2026`
- `DISP001` / `Mushi@2026`
- `EPM001` / `Mgimwa@2026`
- `HOSTEL001` / `Mushi@2026`
- `DSA001` / `Said@2026`
- `ADM001` / `Kimaro@2026`
- `FIN001` / `Mollel@2026`

## Demo Student Accounts

Mwongozo rahisi wa kutumia akaunti hizi: [README ya demo accounts](README_DEMO_ACCOUNTS.md). Ina login za wanafunzi, officers na admin, pamoja na hatua za kujaribu clearance.

These accounts are fictional demonstration accounts created only for development, testing, and project presentation. They must not be used as real student accounts in production. There are fifteen active demo students: the original five and ten additional accounts. New accounts are created automatically on the next application startup; existing passwords and clearance history are preserved.

| Student Name | Registration Number | Initial Password | Programme |
|---|---|---|---|
| Steven Juma Kibabaje | IRDP/ODICT/MA25/0001 | KIBABAJE0001 | ODICT |
| Neema Asha Mfinanga | IRDP/BTCRP/MA25/0002 | MFINANGA0002 | BTCRP |
| Baraka Musa Mushi | IRDP/BTCCD/MA25/0003 | MUSHI0003 | BTCCD |
| Rehema John Mallya | IRDP/ODICT/MA25/0004 | MALLYA0004 | ODICT |
| Daniel Peter Kweka | IRDP/BTCRP/MA25/0005 | KWEKA0005 | BTCRP |
| Amina Hassan Said | IRDP/BTCCD/MA25/0006 | SAID0006 | BTCCD |
| Joseph Paul Mrema | IRDP/ODICT/MA25/0007 | MREMA0007 | ODICT |
| Fatuma Ali Mollel | IRDP/BTCRP/MA25/0008 | MOLLEL0008 | BTCRP |
| Musa Ibrahim Kimaro | IRDP/BTCCD/MA25/0009 | KIMARO0009 | BTCCD |
| Grace Esther Massawe | IRDP/ODICT/MA25/0010 | MASSAWE0010 | ODICT |
| Peter James Mgimwa | IRDP/BTCRP/MA25/0011 | MGIMWA0011 | BTCRP |
| Halima Omar Nyerere | IRDP/BTCCD/MA25/0012 | NYERERE0012 | BTCCD |
| John David Mkude | IRDP/ODICT/MA25/0013 | MKUDE0013 | ODICT |
| Zawadi Rose Mwakalinga | IRDP/BTCRP/MA25/0014 | MWAKALINGA0014 | BTCRP |
| Emmanuel Daniel Msuya | IRDP/BTCCD/MA25/0015 | MSUYA0015 | BTCCD |

Student login uses the Registration Number as the username. The initial password rule is **UPPERCASE LAST NAME + LAST 4 DIGITS OF REGISTRATION NUMBER**.

Example:

- Registration Number: `IRDP/ODICT/MA25/0001`
- Student: `Steven Juma Kibabaje`
- Initial Password: `KIBABAJE0001`

The plain-text demo passwords appear in README.md only because these are fictional demonstration/testing accounts. Real production student passwords must never be documented in README.md or committed to source control. The database stores only secure Argon2id password hashes.

## Future Development — Student Bulk Import

The current presentation workflow uses the fifteen demo student accounts listed above. A future production onboarding flow can allow authorized Admin users to import official Excel/CSV records containing Registration Number, First Name, Middle Name, Last Name, and Programme.

That production flow should validate registration numbers, reject duplicates, create the student accounts, generate each initial password from the uppercase last name plus the final four registration digits, and securely hash every password before storage. The existing prototype import utilities remain in the codebase for extension and testing, but bulk import is intentionally excluded from the current demo navigation.

## Database configuration

The default local configuration expects:

- Host: `127.0.0.1`
- Database: `irdp_e_clearance`
- User: `root`
- Password: empty

If your XAMPP MySQL root account has a password, edit `config/database.php`.

## Workflow

Starting clearance opens all 11 office stages as `PENDING`. Every assigned officer can review independently in any order, including Finance. A rejected office requires student correction while other offices continue; `PAUSED` indicates outstanding student action. Resubmitting one office does not clear rejections at other offices. The last office to approve completes the request only when all 11 offices have approved and all liabilities are cleared; a certificate is generated automatically and a transcript snapshot becomes available.

The automatic upgrade opens formerly `LOCKED` stages on active requests and preserves recorded approvals, rejections, evidence, reviewer assignments and issued documents. Completed and cancelled requests are unchanged. Run `php tests/parallel_clearance_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"` for isolated upgrade checks.

Administrators control the period from **Clearance Period**. Students can still log in while it is closed, but server-side checks reject new starts and resubmissions. Academic Results has been removed from both Admin and Student pages. After all eleven offices approve and liabilities are cleared, the system issues a **Clearance Transcript** with student/programme details, the clearance cycle, each approving office, the officer who approved and the approval date. It contains no marks, grades or GPA. Approvals are stored in an immutable document snapshot; repeated downloads reuse that snapshot. The PDF includes a document number and verification QR code at transcripts/verify.php; administrators can revoke issued documents.

## Reports

Finance (stage 11) uses a control number and the **Reject** / **Approve** buttons. Enter a 6–30 digit control number and choose Reject to send the payment request to the student. The student sees it on the dashboard and My Clearance, pays, and uploads one PDF/JPG/PNG receipt up to 5 MB. Finance can then open the private receipt and approve or reject it. Approval is blocked until a receipt for the current control number and review cycle exists. Changing a control number requires Reject and a new receipt. Rejected receipts and previous control numbers stay in the history. Payment verification is performed by Finance; uploading a receipt alone does not complete clearance. Existing approved records and issued clearance documents remain usable.

Administrators can generate weekly, monthly and custom date-range PDF reports.

## Code quality

PHP files are formatted with clear indentation and logical sections. SQL is formatted vertically for readability, and prepared statements are used for application data.

## Verification

Run `php tests/features_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"` from the project directory. The test creates a randomly named temporary database and removes it afterward; it never bootstraps the application database. The database user needs CREATE and DROP DATABASE privileges.

To test against a separate local MariaDB instance, pass its server DSN as the first argument, for example `php tests/features_test.php "mysql:host=127.0.0.1;port=13317;charset=utf8mb4"`. This also runs the competing-connection review test. Optional credentials use `IRDP_TEST_USER` and `IRDP_TEST_PASSWORD` environment variables.

Workflow configuration supports stages 1 through 11; saving a stage updates its configuration for new requests. Default seeding preserves existing configuration.

Input validation rules, audit findings and test commands: [Input validation audit](docs/INPUT_VALIDATION_AUDIT.md).

Run `php tests/responsive_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"` for browser checks at 320–1440px and 100%/200% text size. This creates and removes an isolated database. It needs Node 22+ and an installed Edge/Chromium browser; on Windows it can use VS Code’s bundled Node runtime. Set `IRDP_NODE_BINARY` or `IRDP_BROWSER_BINARY` to override executable paths, and optionally `IRDP_SCREENSHOT_DIR` to save viewport screenshots.
