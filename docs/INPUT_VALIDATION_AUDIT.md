# Ukaguzi wa input validation

Tarehe: 7 Oktoba 2026.

Ukaguzi huu umehusisha sehemu zote zinazopokea taarifa kwenye mfumo: Admin, Student, Officer, authentication, imports, uploads, reports, certificate verification, transcript verification na URL za kupakua faili. Mapungufu yaliyopatikana yamerekebishwa kwenye server na validation ya JavaScript.

## Mapungufu na marekebisho

| Kilichopatikana | Marekebisho |
| --- | --- |
| Certificate iliyopatikana kwenye database ingeweza kuonyesha “Certificate verified” hata baada ya kushindwa release checks. | Matokeo yanaondolewa verification ikishindwa; ujumbe wa mafanikio unaonekana tu baada ya checks zote kupita. |
| Approval yenye details pungufu au amount isiyo sahihi ingeweza kuhesabiwa kuwa imemaliza liabilities. | Fields zote za ofisi zinahakikiwa tena kabla ya certificate/transcript kutolewa. Missing, malformed na unresolved details zinakataliwa. |
| Baadhi ya IDs zisizo sahihi kwenye URLs zilisababisha exception isiyoshughulikiwa. | Certificate, transcript na officer review URLs sasa zinajibu HTTP 400 kwa IDs zisizo halali. |
| Recovery pagination ilibadilisha “1abc” kuwa page 1 na kuficha invalid filter values. | Page lazima iwe integer halali kati ya 1 na 100000; status lazima iwe mojawapo ya chaguo zilizoruhusiwa. |
| Fedha zilikuwa na decimal checks lakini hazikuwa na upper bound. | Kiasi kinapaswa kuwa 0–999999999999.99 Tsh, hadi decimal places mbili; negative numbers, scientific notation, infinity na digits nyingi zinakataliwa. |
| Trimming ya text ilifanyika kabla ya control-character checks. | Raw text inakaguliwa kwanza kwa UTF-8 na control bytes; Unicode spaces zinaondolewa pembeni; empty/oversized values zinakataliwa. |
| ID helper iliruhusu baadhi ya floats/booleans kutokana na scalar conversion. | ID helper inapokea strings za digits au integer pekee na kulinda range ya database. |
| Evidence upload yenye muundo usio sahihi ingeweza kuchukuliwa kama hakuna faili. | Single/nested upload values, missing/mismatched metadata na invalid metadata types zinakataliwa. |
| Majina ya faili hayakuwa na ukaguzi kamili wa encoding/control characters. | UTF-8, length na control bytes zinakaguliwa; directory names za Windows/Unix zinaondolewa kabla ya original filename kuhifadhiwa. |
| Duplicate office/department/programme inputs zilitoa generic database errors. | Duplicate checks sasa zinaonyesha field husika kabla ya insert; database uniqueness constraints zinaendelea kulinda records. |
| Import preview haikuonyesha emails zilizojirudia ndani ya faili. | Emails zinazoonekana zaidi ya mara moja, hata zikitofautiana kwa case, zinakataliwa kwenye preview. |
| Concurrent account/import writes zingeweza kupita email check kwa wakati mmoja. | Account validation na inserts zinaserialize ndani ya transaction kwa lock ya role record iliyopo; import hutumia lock hiyo pia. |
| Browser haikulazimisha department kwa departmental officers. | Required fields za Student/Officer/Supervisor zinafuata office na role; server bado inaamua uhalali. |
| Review form ingeweza kuonyesha taarifa kwa assigned officer aliyepoteza active office authority. | GET ya review form sasa inakagua active office na department authority kabla ya kuonyesha taarifa. |
| Custom report bila tarehe ingepewa default dates kimya kimya. | Custom report inahitaji tarehe zote mbili; real dates na order vinahakikiwa. |
| Duplicate XLSX cell references zingeweza kufunika value ya awali. | Duplicate cell columns na invalid row references zinakataliwa. |

## Masharti ya kila sehemu

| Sehemu | Masharti yanayotekelezwa |
| --- | --- |
| Request startup | Nested GET/POST values zinakataliwa. GET values lazima ziwe UTF-8 bila control characters. State-changing POST requests zinahitaji session CSRF token. Oversized uploads zina feedback ya HTTP 413. |
| Login / current password | Username hadi characters 100. Password string, UTF-8, nonempty, bila control characters; hadi bytes 4096 kwa compatibility ya passwords za zamani. Invalid credentials hazifungui session. |
| New passwords / reset | Characters 8–64, confirmation inayolingana, common/repeated passwords zinakataliwa. Passwords hazitrimwi wala kukatwa. Reset token lazima iwe lowercase hexadecimal characters 64, valid, unexpired, unused na approved. |
| Account creation | Jina hadi characters 160, Unicode letters, spaces, apostrophes, dots na hyphens; lazima lianze na letter. Staff username characters 3–100. Student registration format na programme lazima zilingane. Role, office, department na academic cycle vinahakikiwa dhidi ya database. Duplicate username/registration/email zinakataliwa. |
| Offices / departments / programmes | Required text, schema length limits, permitted code formats na duplicate checks. Programme code ni uppercase characters 2–20; department code ni characters 2–30. |
| Workflow | Step number 1–11, title hadi characters 180 na active office inayopatikana. |
| Clearance period | Mode inayoruhusiwa, active cycle au consecutive new cycle, remarks hadi characters 1000, real local date/time, na closing baada ya opening kwa schedule. Years 0000 na impossible dates zinakataliwa. |
| Student start | Existing active academic cycle inayolingana na open clearance period, one request per student/cycle na active reviewers kwa stages zote 11. |
| Student correction | Stage ya mwanafunzi husika, REJECTED/PAUSED state, open clearance period, response hadi characters 4000 na hadi evidence files tano. Blank response haisababishi resubmission. |
| Officer decisions | Assigned active reviewer mwenye office/department access, approved prerequisites, current review cycle, permitted action, valid amounts/options, na rejection reason/corrective instructions. |
| Profile uploads | JPG/JPEG/PNG hadi 5 MB; detected MIME na extension zilingane; image dimensions 32–6000 kwa kila upande, hadi pixels milioni 16. Profile image inadecode na kuandikwa upya kwenye square ya 256 pixels. |
| Evidence uploads | Hadi faili tano, kila moja hadi 5 MB: PDF/JPG/JPEG/PNG. Uploaded-file origin, actual byte size, MIME/extension, PDF header na image dimensions vinakaguliwa. Storage filenames zinatengenezwa na server. |
| Student imports | CSV/XLSX hadi 5 MB, rows hadi 1000 na headers sita zinazolingana na template. Active references, name/email/registration/cycle checks, formulas na duplicates zinakaguliwa; commit inavalidate tena kabla ya insert. XLSX XML/archive limits na unsafe XML rejection zimehifadhiwa. |
| Reports | Weekly/monthly/custom type pekee; custom from/to zinahitajika katika Y-m-d, real dates, years 1000–9999 na from <= to. |
| Certificates | Valid record IDs, ownership/admin access, all eleven approved stages na cleared liabilities. Public verification inakagua certificate number na code yenye uppercase hexadecimal characters 10. |
| Clearance transcripts | Valid student identifier kwa Admin; Student hutumia identity ya session yake. All eleven approvals, snapshot hash, valid/revoked status na verification token format zinahakikiwa. Transcript haina marks/GPA. |
| Private file downloads | Valid IDs, ownership/office/admin permissions kama inavyofaa, generated storage path na file existence. Invalid/missing references zinatoa 400/403/404 badala ya exposing database errors. |

Free-text comments, responses na descriptions zinahifadhi maandishi halali na punctuation. Output ina HTML escaping; database values zinatumia prepared statements. Input validation haitegemei kufuta maneno yanayofanana na SQL.

## Vipimo

Vipimo vya PHP na HTTP vinatumia databases za majaribio zinazotengenezwa na kuondolewa baada ya suite. Havibadilishi accounts au approvals kwenye live database.

- PHP syntax: files 76.
- Shared validation tests: invalid types, controls, Unicode, IDs, dates, bounds za fedha, liabilities na upload metadata.
- Password/accessibility tests: Unicode boundaries, existing hashes, confirmation na linked field errors.
- Spreadsheet tests: namespaces, invalid/unsafe XML, duplicate cells, zero-row references na extra columns.
- JavaScript form-control tests: role/department requirements, amounts, uploads, period/report dates na Unicode password confirmation.
- Feature suite: HTTP requests 196, ikiwa ni pamoja na forms zinazotumwa moja kwa moja bila browser validation, certificate failure feedback, account locks na clearance workflow.
- Demo suite: wanafunzi wote watano, login/dashboard, password changes, privacy na duplicate clearance cycles.

Commands kutoka project directory:

~~~powershell
C:\xampp\php\php.exe tests/validation_test.php
C:\xampp\php\php.exe tests/password_accessibility_test.php
C:\xampp\php\php.exe tests/spreadsheet_xml_test.php
node tests/browser_validation_test.js
C:\xampp\php\php.exe tests/features_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"
C:\xampp\php\php.exe tests/demo_students_test.php "mysql:host=127.0.0.1;port=3306;charset=utf8mb4"
~~~

JavaScript tests zinatumia form-control harness inayotekeleza validation.js halisi; si manual UI test ya kila browser. PDF upload checks zinahakiki MIME/extension/header na size; hazidai kuthibitisha kila internal PDF object.

Historical data haijafutwa. Approval records za zamani zenye missing/invalid details sasa zinahitaji kukamilishwa ili certificate/transcript iweze kutolewa. Email serialization inalinda application's account creation na import paths; haiongezi unique email constraint kwenye database wala kubadilisha historical accounts.
