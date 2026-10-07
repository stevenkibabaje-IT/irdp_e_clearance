# Contributing

Contributions to the IRDP Student Clearance System are welcome.

## Fork, branch and submit a pull request

1. Fork [stevenkibabaje-IT/irdp_e_clearance](https://github.com/stevenkibabaje-IT/irdp_e_clearance) into your GitHub account.
2. Clone your fork:

   ```powershell
   git clone https://github.com/YOUR-USERNAME/irdp_e_clearance.git
   cd irdp_e_clearance
   git remote add upstream https://github.com/stevenkibabaje-IT/irdp_e_clearance.git
   git switch -c describe-your-change
   ```

3. Follow [README.md](README.md) to run PHP/MySQL with XAMPP. Each developer uses their own local database and uploads.
4. Make a focused change. Preserve office authorization, the eleven-stage approval order, private receipt access, CSRF protection and input validation.
5. Run the checks relevant to your change:

   ```powershell
   php tests/validation_test.php
   php tests/password_accessibility_test.php
   php tests/spreadsheet_xml_test.php
   node tests/browser_validation_test.js
   php tests/features_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"
   ```

   The feature suite creates and removes its own test database. The database account needs CREATE/DROP DATABASE privileges. For layout changes, run `php tests/responsive_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"` with Node 22+ and Edge/Chromium. See README.md for runtime overrides.

6. Commit and push your branch:

   ```powershell
   git add path/to/changed-file.php
   git commit -m "Describe the resulting change"
   git push -u origin describe-your-change
   ```

7. Open a pull request to the upstream repository's default branch. Explain the problem, the resulting behavior and the checks you ran. Include screenshots for layout changes using demo data.

Anyone with a GitHub account can contribute through a fork and pull request when this repository is public. Maintainers review and merge changes. Direct write access is granted separately to invited collaborators.

## Keep private data local

Do not commit real student records, payment receipts, profile pictures, database backups, passwords, reset codes, tokens or connection secrets. Runtime storage, logs and local credential files are excluded by .gitignore. The tracked database.sql is the base schema, rather than a live database export.

Configure connection secrets through environment variables rather than editing tracked configuration defaults. Existing bundled accounts are development/demo accounts described in README.md.

## Coding conventions

Use UTF-8, prepared SQL statements, escaped output and the existing shared helpers. Validate changes on the server even when browser validation is present. Use the local SVG icons instead of emojis. Keep pages usable on phones, tablets and desktops. Clearance transcripts contain office approvals without academic marks or GPA.
