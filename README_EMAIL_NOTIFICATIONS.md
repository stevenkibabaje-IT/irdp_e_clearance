# Email notifications za clearance

Mwanafunzi mwenye email kwenye akaunti yake anapata notification ndani ya mfumo na ujumbe wa email kwa matukio haya:

- Kipindi cha clearance kufunguliwa.
- Finance kutoa control number na kuidhinisha ada ya kuanza clearance.
- Ofisi kuidhinisha au kukataa hatua ya clearance.
- Finance kuomba malipo ya deni jingine.
- Clearance kukamilika na transcript kuwa tayari.

Demo accounts zisizo na email zinaendelea kufanya kazi. Mwanafunzi anaongeza au kubadilisha email kupitia **My Profile → Email notifications**, akithibitisha kwa password yake ya sasa. Email hii haitathibitishwa moja kwa moja kwa password recovery.

## Kusanidi Gmail ya mtumaji

1. Ingia kwenye Gmail ya mtumaji. Kwa majaribio haya mtumaji ni `stevenkibabaje@gmail.com`.
2. Washa **2-Step Verification** kwenye Google Account.
3. Fungua [Google App Passwords](https://myaccount.google.com/apppasswords), tengeneza App Password kwa jina `IRDP Clearance` na nakili password iliyoonyeshwa.
4. Fungua `C:\xampp\htdocs\Irdp_e_clearance\config\mail.local.php` kwenye editor. Weka App Password kwenye `'password' => ''`, kisha save. Password ya kawaida ya Gmail haitumiki hapa.
5. Hakikisha `enabled` ni `true`, `host` ni `smtp.gmail.com`, `port` ni `587`, `encryption` ni `tls` na `username` pamoja na `from_email` ni email ya mtumaji.

Usitume password kwenye chat au kuiweka kwenye README. `mail.local.php` haijumuishwi kwenye Git na folder ya config imezuiwa kufunguliwa kupitia Apache. Kwa kompyuta nyingine, nakili `config/mail.example.php` kuwa `config/mail.local.php` na jaza taarifa za mtumaji.

Google inaweza kuzuia App Passwords kutokana na sera ya akaunti. Fuata [maelekezo rasmi ya Google](https://support.google.com/mail/answer/185833) ikiwa chaguo hilo halionekani. Usizime uthibitishaji wa certificate ya SMTP ili kufanya email ifanye kazi.

## Kujaribu email ya Andrea

1. Washa Apache na MySQL kwenye XAMPP.
2. Ingia kama admin na fungua **Email Notifications**.
3. Chagua **ANDREA RENATUS JUMA — IRDP/ODICT/MA26/0002** kwenye sehemu ya mwanafunzi.
4. Bonyeza **Send test email**. Ujumbe unatumiwa kwa `andrearenatusjuma25@gmail.com`.
5. Angalia **Recent delivery history** na inbox ya Andrea, pamoja na Spam. Hali ya `SENT` ina maana server ya SMTP imekubali ujumbe; haithibitishi kuwa umeonekana kwenye inbox.

Ukiona `SMTP authentication failed`, hakiki email ya mtumaji na App Password. Ukiona `SMTP connection failed`, hakiki network na port. Ujumbe ulioshindwa unatumiwa tena kwa vipindi vinavyoongezeka, hadi majaribio matano; admin anaweza kubonyeza **Retry email** baada ya kusahihisha tatizo. **Send pending emails** hutuma hadi ujumbe watatu kwa ombi la admin.

## Utumaji wa moja kwa moja

Email huhifadhiwa kwenye `email_outbox` ndani ya transaction ileile ya notification. Clearance ikirudishwa nyuma, email yake pia hufutwa. Utumaji hufanywa baada ya transaction, ili hitilafu ya SMTP isiathiri maamuzi ya ofisi. Notification ya ndani ya mfumo inaendelea kuwepo.

Kwa XAMPP kwenye Windows, endesha ukiwa kwenye folder ya mradi:

```powershell
powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File jobs\install_email_schedule.ps1
```

Task **IRDP e-Clearance Email Notifications** hutuma ujumbe kila dakika moja wakati mtumiaji huyu wa Windows ame-login. MySQL, kompyuta na network vinahitaji kuwa vinapatikana. Kwa hosting, weka cron/task inayotumia PHP kuendesha `jobs/send_notification_emails.php` kila dakika.

`ExecutionPolicy Bypass` inatumika kwa mchakato wa scripts hizi za ndani pekee; haibadilishi execution policy ya Windows iliyohifadhiwa.

Kuendesha worker kwa mkono:

```powershell
C:\xampp\php\php.exe jobs\send_notification_emails.php
```

Worker huchakata hadi email 20 kwa mzunguko na huzuia workers wawili kutuma foleni hiyo kwa wakati mmoja. Email zilizotumwa hazichakatwi tena. Ujumbe kwa anwani ya zamani hufutwa mwanafunzi akibadilisha email. Worker ikisimama baada ya SMTP kukubali ujumbe lakini kabla ya kurekodi hali, ujumbe unaweza kutumwa tena wakati wa recovery.

`APPLICATION_ORIGIN` kwenye `config/application.php` hutengeneza link ya mfumo ndani ya ujumbe. Kwa sasa ni `http://localhost/Irdp_e_clearance`, inayofunguka kwenye kompyuta yenye XAMPP; kwa wanafunzi wanaotumia vifaa vingine, weka URL ya mfumo wanayoweza kufikia.

## Ukaguzi kwa developer

```powershell
C:\xampp\php\php.exe tests\email_notifications_test.php
```

Test hutumia database tofauti na SMTP ya ndani inayopokea ujumbe bila kuwasiliana na Gmail. Inahakiki rollback, kutorudia ujumbe uliotumwa, retry, usiri wa errors, mabadiliko ya email, permissions na profile kupitia HTTP. Database ya majaribio huondolewa baada ya kumaliza.

SMTP hutumia [PHPMailer 7.1.1](https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1), iliyohifadhiwa kwenye `includes/vendor/phpmailer` pamoja na license yake. Password recovery iliyopo inaendelea kutumia utaratibu wake wa uthibitishaji wa identity; email notifications hazibadilishi masharti yake.
