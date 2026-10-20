# IRDP e-Clearance — System Guide and Accounts

This is the single main README for this installation. It covers setup, student clearance, office responsibilities, email delivery and all current accounts. Account credentials are private: this file is blocked through Apache and excluded from Git.

## Contents

- [Start the system and sign in](#start-the-system-and-sign-in)
- [Entry fee and clearance](#entry-fee-and-clearance)
- [Office responsibilities](#office-responsibilities)
- [Administrator tasks](#administrator-tasks)
- [Email and account recovery](#email-and-account-recovery)
- [All user accounts](#all-user-accounts)
- [Checks and troubleshooting](#checks-and-troubleshooting)

## Start the system and sign in

1. Start **Apache** and **MySQL** in XAMPP.
2. Open **http://localhost/irdp_e_clearance/auth/login.php**.
3. Enter the username and password from your account table below. Students use the complete registration number, including slashes.
4. Use another browser or a private window for a second account. Ordinary tabs share the same login.

The interface, help, accessibility controls and system-generated emails use **English**. Names, historical records and remarks entered by users retain their original spelling.

Passwords are case-sensitive. The 21 imported student accounts use **CSV Last Name in uppercase + the final four registration digits**. Earlier accounts keep their passwords. **IRDP/TCICT/MA26/0032** uses **ONESMO0032**, as requested. If you change a password through My Profile, ask the administrator to update this local reference.

Sessions expire after **30 minutes of inactivity** or **8 hours after login**. Select Continue session when warned; the eight-hour limit requires a fresh login.

## Entry fee and clearance

1. Admin opens the required academic cycle.
2. A student who has never started clearance receives a **demo control number automatically after login** while clearance is open.
3. Dashboard and Clearance Fee show the number, quoted amount, generation day, date and time in **Tanzania / EAT**. Repeated logins retain the same number and timestamp.
4. Upload **one PDF, JPG or PNG receipt, up to 5 MB**, for the current control number.
5. A received receipt unlocks **Start Clearance immediately**. Payment remains **AWAITING_REVIEW** until Finance verifies it; uploading does not mark payment as approved.
6. Select the open cycle and submit once. The other **10 offices** review independently; Finance clearance duties remain locked until all ten approve. Entry-fee verification remains available to Finance separately.

The default entry fee is **TSh 10,000**. Finance sets rates per cycle; existing quotes keep their amount. These control numbers are **local demonstration references**, without bank or GePG integration.

Each registration number can start **one clearance only**. Existing clearances continue through My Clearance without another entry fee. After clearance starts, its entry-fee number and receipt are locked; Finance can still approve the payment. Finance's later clearance stage handles other debts separately.

For a rejected stage, complete the instructions and select **Submit evidence to office**. Up to five PDF/JPG/PNG files are accepted, each up to 5 MB. Existing approvals and review history remain in place. **All 11 offices must approve** before completion and Download Student Clearance Certificate become available.

Certificate identity and document versions share the **transcripts** table. The database now uses **28 tables**, after merging certificates and removing the unused import_runs table. Import outcomes remain in audit_logs and student importing remains available. Existing certificate numbers, verification codes, document tokens and snapshots are preserved. See [Document storage and migration](docs/UNIFIED_DOCUMENTS.md).

## Office responsibilities

| Stage | Office                 | Relevant review                                          | Payment handling                                            |
| ----- | ---------------------- | -------------------------------------------------------- | ----------------------------------------------------------- |
| 1     | Library                | Book returns and outstanding library liability           | Record liability; Finance handles payments                  |
| 2     | Sports and Games       | Sports equipment and unresolved equipment liability      | Record liability; Finance handles payments                  |
| 3     | Computer Lab           | Equipment, accessories and equipment liability           | Record liability; Finance handles payments                  |
| 4     | Supplies               | Issued supplies and institutional property liability     | Record liability; Finance handles payments                  |
| 5     | Transport              | Outstanding transport obligations                        | Record liability; Finance handles payments                  |
| 6     | Dispensary             | Clearance decision and corrective instructions           | No amount or control-number field                           |
| 7     | Head of Department     | Academic and departmental requirements                   | No amount or control-number field                           |
| 8     | Hostels                | Accommodation property returns and outstanding liability | Record liability; Finance handles payments                  |
| 9     | Student Affairs        | Student affairs clearance decision                       | No payment fields                                           |
| 10    | Admissions             | Registration and admissions clearance decision           | No payment fields                                           |
| 11    | Finance and Accounting | Other debts and payment verification                     | Control number and receipt when another payment is required |

**FMS001** reviews Head of Department for **Finance and Management Studies (FMS)** students, including ICT. **EPM001** reviews that stage for **Environmental Planning and Management (EPM)**. **FIN001** handles payments and is separate from FMS001.

Officers review only assigned office work and, for departmental review, the matching department. Rejection requires a reason and corrective instructions. Finance uses **Approve - no other debt**, or issues a control number and verifies its receipt; Finance does not use Reject. Historical decisions and evidence are preserved.

## Administrator tasks

- Create accounts under Users with the correct role, department and programme. Assign an office to each officer and a matching department to departmental reviewers.
- The account form's email field is labelled **Email**. Email verification for recovery remains a separate check.
- Manage programmes, departments and the clearance period; monitor requests and verify identity before administrator-assisted recovery.

## Email and account recovery

System notification and OTP templates use **English**. Queued legacy templates are converted to English when sent. Decision emails contain an English summary and a link to My Clearance. Full officer comments and corrective instructions remain available inside the system, preserved as written.

SMTP settings and the Gmail App Password are in **config/mail.local.php**. The App Password is not included here. The Windows task **IRDP e-Clearance Email Notifications** checks the queue every minute while the configured Windows user is logged in. The PC, MySQL and network must be available. In-app notifications remain available if SMTP fails.

Students use Forgot Password with their registered email. The six-digit OTP expires in 10 minutes, allows five attempts and can be resent after 60 seconds. Use the same browser throughout. Staff and students without email access should contact support for identity verification and administrator-assisted recovery.

## All user accounts

**56 ACTIVE accounts: 1 administrator, 12 officers and 43 students.** All listed passwords have been verified against this installation. Current as of 18 October 2026.

### ADMIN — 1 account

| Full name            | Username | Password       | Programme / Office | Department | Status |
| -------------------- | -------- | -------------- | ------------------ | ---------- | ------ |
| System Administrator | admin    | Admin@IRDP2026 | ADMIN              | —          | ACTIVE |

### OFFICER — 12 accounts

| Full name              | Username  | Password     | Programme / Office                             | Department | Status |
| ---------------------- | --------- | ------------ | ---------------------------------------------- | ---------- | ------ |
| Robert Kimaro          | ADM001    | Kimaro@2026  | Admissions Officer                             | —          | ACTIVE |
| Daniel Massawe         | COMP001   | Massawe@2026 | Computer Lab/Room in Charge                    | —          | ACTIVE |
| Asha Mushi             | DISP001   | Mushi@2026   | Dispensary                                     | —          | ACTIVE |
| Fatuma Said            | DSA001    | Said@2026    | Director of Student Affairs                    | —          | ACTIVE |
| Michael Mgimwa         | EPM001    | Mgimwa@2026  | Head of Department                             | EPM        | ACTIVE |
| David Mollel           | FIN001    | Mollel@2026  | Director of Finance and Accounts               | —          | ACTIVE |
| Head of Department FMS | FMS001    | HOD123       | Head of Department                             | FMS        | ACTIVE |
| John Mushi             | HOSTEL001 | Mushi@2026   | Hostels Superintendent (Accommodation Officer) | —          | ACTIVE |
| Joseph Mrema           | LIB001    | Mrema@2026   | Librarian                                      | —          | ACTIVE |
| Rehema John            | SPORT001  | John@2026    | Sports and Games (Matron)                      | —          | ACTIVE |
| Grace Mallya           | SUP001    | Mallya@2026  | Supplies Officer                               | —          | ACTIVE |
| Peter Kweka            | TRANS001  | Kweka@2026   | Transport Officer                              | —          | ACTIVE |

### STUDENT — 43 accounts

#### BTCCD

| Full name             | Username             | Password    | Programme / Office | Department | Status |
| --------------------- | -------------------- | ----------- | ------------------ | ---------- | ------ |
| Baraka Musa Mushi     | IRDP/BTCCD/MA25/0003 | MUSHI0003   | BTCCD              | EPM        | ACTIVE |
| Amina Hassan Said     | IRDP/BTCCD/MA25/0006 | SAID0006    | BTCCD              | EPM        | ACTIVE |
| Musa Ibrahim Kimaro   | IRDP/BTCCD/MA25/0009 | KIMARO0009  | BTCCD              | EPM        | ACTIVE |
| Halima Omar Nyerere   | IRDP/BTCCD/MA25/0012 | NYERERE0012 | BTCCD              | EPM        | ACTIVE |
| Emmanuel Daniel Msuya | IRDP/BTCCD/MA25/0015 | MSUYA0015   | BTCCD              | EPM        | ACTIVE |

#### BTCRP

| Full name              | Username             | Password       | Programme / Office | Department | Status |
| ---------------------- | -------------------- | -------------- | ------------------ | ---------- | ------ |
| Neema Asha Mfinanga    | IRDP/BTCRP/MA25/0002 | MFINANGA0002   | BTCRP              | EPM        | ACTIVE |
| Daniel Peter Kweka     | IRDP/BTCRP/MA25/0005 | KWEKA0005      | BTCRP              | EPM        | ACTIVE |
| Fatuma Ali Mollel      | IRDP/BTCRP/MA25/0008 | MOLLEL0008     | BTCRP              | EPM        | ACTIVE |
| Peter James Mgimwa     | IRDP/BTCRP/MA25/0011 | MGIMWA0011     | BTCRP              | EPM        | ACTIVE |
| Zawadi Rose Mwakalinga | IRDP/BTCRP/MA25/0014 | MWAKALINGA0014 | BTCRP              | EPM        | ACTIVE |

#### ODDAM

| Full name               | Username             | Password     | Programme / Office | Department | Status |
| ----------------------- | -------------------- | ------------ | ------------------ | ---------- | ------ |
| Abubakar Hamis Kitogota | IRDP/ODDAM/MA26/0009 | KITOGOTA0009 | ODDAM              | EPM        | ACTIVE |

#### ODICT

| Full name                | Username             | Password      | Programme / Office | Department | Status |
| ------------------------ | -------------------- | ------------- | ------------------ | ---------- | ------ |
| Steven Juma Kibabaje     | IRDP/ODICT/MA25/0001 | KIBABAJE0001  | ODICT              | FMS        | ACTIVE |
| Rehema John Mallya       | IRDP/ODICT/MA25/0004 | MALLYA0004    | ODICT              | FMS        | ACTIVE |
| Joseph Paul Mrema        | IRDP/ODICT/MA25/0007 | MREMA0007     | ODICT              | FMS        | ACTIVE |
| Grace Esther Massawe     | IRDP/ODICT/MA25/0010 | MASSAWE0010   | ODICT              | FMS        | ACTIVE |
| John David Mkude         | IRDP/ODICT/MA25/0013 | MKUDE0013     | ODICT              | FMS        | ACTIVE |
| Miriam Ngugi Elias       | IRDP/ODICT/MA26/0001 | ELIAS0001     | ODICT              | FMS        | ACTIVE |
| ANDREA RENATUS JUMA      | IRDP/ODICT/MA26/0002 | JUMA0002      | ODICT              | FMS        | ACTIVE |
| Erika Emmanuel Mazengo   | IRDP/ODICT/MA26/0004 | MAZENGO0004   | ODICT              | FMS        | ACTIVE |
| Shida Wilphonce Manonga  | IRDP/ODICT/MA26/0005 | MANONGA0005   | ODICT              | FMS        | ACTIVE |
| Ritha Thomas Pangani     | IRDP/ODICT/MA26/0006 | PANGANI0006   | ODICT              | FMS        | ACTIVE |
| Jeska Matayo Nkya        | IRDP/ODICT/MA26/0008 | NKYA0008      | ODICT              | FMS        | ACTIVE |
| ELIZABETH BOAZ MWAMBIJE  | IRDP/ODICT/MA26/0009 | MWAMBIJE0009  | ODICT              | FMS        | ACTIVE |
| HERIETH KILIAN MWACHA    | IRDP/ODICT/MA26/0010 | MWACHA0010    | ODICT              | FMS        | ACTIVE |
| Lilian Nurdin Mwalongo   | IRDP/ODICT/MA26/0014 | MWALONGO0014  | ODICT              | FMS        | ACTIVE |
| Kelvin Fredrick Bakari   | IRDP/ODICT/MA26/0015 | BAKARI0015    | ODICT              | FMS        | ACTIVE |
| KHALFANI SALUMU ATHUMAN  | IRDP/ODICT/MA26/0016 | ATHUMAN0016   | ODICT              | FMS        | ACTIVE |
| John Alfonce Sabuni      | IRDP/ODICT/MA26/0019 | SABUN0019     | ODICT              | FMS        | ACTIVE |
| SAIDI SAID JUMA          | IRDP/ODICT/MA26/0021 | JUMA0021      | ODICT              | FMS        | ACTIVE |
| PRISCA MASHAKA MWAKILASA | IRDP/ODICT/MA26/0022 | MWAKILASA0022 | ODICT              | FMS        | ACTIVE |
| Hilling Onesmo Steven    | IRDP/ODICT/MA26/0023 | STEVENS0023   | ODICT              | FMS        | ACTIVE |
| Maulidi Hemedi Likonda   | IRDP/ODICT/MA26/0024 | LIKONDA0024   | ODICT              | FMS        | ACTIVE |

#### TCICT

| Full name             | Username             | Password      | Programme / Office | Department | Status |
| --------------------- | -------------------- | ------------- | ------------------ | ---------- | ------ |
| Jenipher Deogratius   | IRDP/TCICT/MA26/0001 | MWIMANZI0001  | TCICT              | FMS        | ACTIVE |
| Agness Kenneth        | IRDP/TCICT/MA26/0003 | BINTABARA0003 | TCICT              | FMS        | ACTIVE |
| Brayan Prosper Kilolo | IRDP/TCICT/MA26/0005 | KILOLO0005    | TCICT              | FMS        | ACTIVE |
| Agatha willium Msela  | IRDP/TCICT/MA26/0007 | MSELA0007     | TCICT              | FMS        | ACTIVE |
| Linus Richard         | IRDP/TCICT/MA26/0008 | KABENDELA0008 | TCICT              | FMS        | ACTIVE |
| Majid juma mrisho     | IRDP/TCICT/MA26/0009 | MRISHO0009    | TCICT              | FMS        | ACTIVE |
| Mzungu Elius          | IRDP/TCICT/MA26/0010 | MAPERA0010    | TCICT              | FMS        | ACTIVE |
| Britney Dickson       | IRDP/TCICT/MA26/0011 | MLELWA0011    | TCICT              | FMS        | ACTIVE |
| Mohamed Sadick        | IRDP/TCICT/MA26/0013 | MOHAMED0013   | TCICT              | FMS        | ACTIVE |
| Nasri zahana habir    | IRDP/TCICT/MA26/0015 | HABIR0015     | TCICT              | FMS        | ACTIVE |
| TIVEN ONESMO          | IRDP/TCICT/MA26/0032 | ONESMO0032    | TCICT              | FMS        | ACTIVE |

## Checks and troubleshooting

| Problem                      | Action                                                                            |
| ---------------------------- | --------------------------------------------------------------------------------- |
| Invalid username or password | Use the exact table entry and letter case; remove old browser autofill            |
| Clearance closed             | Ask Admin to open the required cycle                                              |
| Start Clearance unavailable  | Submit a receipt for the current entry-fee control number while clearance is open |
| No active officer assigned   | Check active status, office authority and the departmental reviewer's department  |
| Certificate unavailable      | Check My Clearance; all 11 offices must approve                                   |
| Email not received           | Check notification email, inbox/spam, SMTP settings and the worker                |

Run from the project directory:

```powershell
& C:/xampp/php/php.exe tests/automatic_entry_fee_test.php
& C:/xampp/php/php.exe tests/email_notifications_test.php
& C:/xampp/php/php.exe tests/student_password_otp_test.php
& C:/xampp/php/php.exe tests/clearance_once_test.php
& C:/xampp/php/php.exe tests/validation_test.php
powershell.exe -NoProfile -ExecutionPolicy Bypass -File jobs/run_email_worker.ps1 -Check
```

Integration tests use disposable databases. They do not submit real student clearances or send through the live Gmail account. Historical README copies are stored as text references in **storage/private/documentation_archive**; this file is the current guide.
