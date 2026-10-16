# Demo Accounts — Mwongozo wa kutumia mfumo

Akaunti hizi ni za majaribio na presentation tu. Kuna demo accounts **15** za msingi: wale 5 wa awali na wengine **10 wapya**, kuanzia namba `0006` hadi `0015`. Pia kuna akaunti **6 zenye email** kwenye jedwali la **Accounts with email** hapa chini: Andrea na wanafunzi watano kutoka kwenye Excel.

## 1. Fungua mfumo

1. Washa **Apache** na **MySQL** kwenye XAMPP.
2. Fungua [IRDP e-Clearance](http://localhost/Irdp_e_clearance/).
3. Bonyeza **Login**, kisha tumia username na password kwenye jedwali hapa chini.

Database ya demo kwenye kompyuta hii ina akaunti **34**: wanafunzi 21, officers 12 na admin 1. Hizi ni akaunti 33 zilizorejeshwa awali pamoja na **FMS001**, Head of Department wa FMS. Akaunti sita za sehemu ya **Accounts with email** zipo kwenye database hii. Akaunti za msingi hutengenezwa wakati wa setup; akaunti nyingine huongezwa kupitia **Admin → Users**. Kunakili repository pekee hakunakili akaunti zilizoongezwa kwenye database ya kompyuta hii.

## 2. Akaunti za wanafunzi

**Username ni Registration Number yote**, pamoja na `/`. Andika password kwa herufi kubwa kama ilivyo kwenye jedwali. Hizi ni password za mwanzo; ukibadilisha password, tumia uliyoweka.

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

Mfano wa kuingia kwa akaunti mpya:

- Username: `IRDP/BTCCD/MA25/0006`
- Password: `SAID0006`

Password ya mwanzo inatengenezwa kwa **jina la mwisho kwa herufi kubwa + tarakimu 4 za mwisho za registration number**. Mwanafunzi anaweza kubadilisha password kupitia **profile ya juu kulia → Change Password**.

### Accounts with email

Akaunti hizi zimeongezwa kwenye database ya sasa kwa demonstration ya notifications. Ziko tofauti na demo accounts 15 zinazotengenezwa moja kwa moja.

| Student Name | Registration Number | Initial Password | Programme | Email |
|---|---|---|---|---|
| ANDREA RENATUS JUMA | IRDP/ODICT/MA26/0002 | — | ODICT | andrearenatusjuma25@gmail.com |
| HERIETH KILIAN MWACHA | IRDP/ODICT/MA26/0010 | MWACHA0010 | ODICT | stevenkibabaje@gmail.com |
| ELIZABETH BOAZ MWAMBIJE | IRDP/ODICT/MA26/0009 | MWAMBIJE0009 | ODICT | elizabethboazy15@gmail.com |
| KHALFANI SALUMU ATHUMAN | IRDP/ODICT/MA26/0016 | ATHUMAN0016 | ODICT | salumukhalfani996@gmail.com |
| SAIDI SAID JUMA | IRDP/ODICT/MA26/0021 | JUMA0021 | ODICT | sidejuum20@gmail.com |
| PRISCA MASHAKA MWAKILASA | IRDP/ODICT/MA26/0022 | MWAKILASA0022 | ODICT | Priscamwakilasa7@gmail.com |

Academic cycle ya akaunti zote sita ni `2026/2027`. Alama **—** inaonyesha password ya mwanzo ilibadilishwa; password ya sasa haijaorodheshwa. Andrea atumie password yake ya sasa au **Forgot Password** kupitia email iliyosajiliwa. Password yake haijabadilishwa wakati wa kusahihisha mwongozo huu. Mwanafunzi anaweza kubadilisha password kupitia **profile ya juu kulia → Change Password**.

Email kwenye jedwali zinaonyesha zilizohifadhiwa kwenye database ya demo tarehe 16 Oktoba 2026. Akaunti ya HERIETH kwa sasa inatumia `stevenkibabaje@gmail.com` kwa majaribio; kubadilisha email kunafanywa kupitia **My Profile** kwa nenosiri la sasa.

Email zimehifadhiwa kwenye akaunti. Wanafunzi wanapoanza clearance na ofisi zinapotoa maamuzi, notifications huonekana ndani ya mfumo na email hutumwa moja kwa moja. Ujumbe wa ofisi unaonyesha stage, ofisi, uamuzi na maelekezo au control number husika. Mtumaji wa majaribio ni `stevenkibabaje@gmail.com`. Gmail App Password inawekwa kwenye `config/mail.local.php`. Fuata [mwongozo wa email](README_EMAIL_NOTIFICATIONS.md) kusanidi mtumaji na kupima utumaji kupitia hatua halisi za clearance.

## 3. Akaunti ya admin

| Username | Password | Matumizi |
|---|---|---|
| admin | Admin@IRDP2026 | Kufungua clearance, kuona users na kufuatilia approvals |

Kabla ya kuanza demo, ingia kama admin:

1. Fungua **Clearance Period**.
2. Chagua **OPEN CLEARANCE** na academic cycle ya demo, kwa mfano `2026/2027`.
3. Jaza **Remarks / reason**, kisha bonyeza **Save clearance period**.
4. Bonyeza **Log out**.

## 4. Akaunti za officers

Kila officer anafanya review ya ofisi yake bila kusubiri nyingine.

| Ofisi | Username | Password ya demo |
|---|---|---|
| Librarian | LIB001 | Mrema@2026 |
| Sports and Games | SPORT001 | John@2026 |
| Computer Lab | COMP001 | Massawe@2026 |
| Supplies | SUP001 | Mallya@2026 |
| Transport | TRANS001 | Kweka@2026 |
| Dispensary | DISP001 | Mushi@2026 |
| Head of Department — EPM | EPM001 | Mgimwa@2026 |
| Head of Department — FMS | FMS001 | Mwangaza@2026 |
| Hostels / Accommodation | HOSTEL001 | Mushi@2026 |
| Student Affairs | DSA001 | Said@2026 |
| Admissions | ADM001 | Kimaro@2026 |
| Finance | FIN001 | Mollel@2026 |

**FMS001** ni Head of Department wa **Department of Finance and Management Studies**. Anakagua hatua ya **7** kwa wanafunzi wa idara hiyo; kwenye database hii ODICT iko FMS. **EPM001** anakagua wanafunzi wa EPM. **FIN001** anasimamia ada ya kuanza clearance na madeni ya hatua ya **11**. Akaunti hizi zina majukumu tofauti.

Password ya muda ya **FMS001** imewekwa tarehe **16 Oktoba 2026**. Ukiingia kwa `FMS001` / `Mwangaza@2026`, mfumo utakufungua **Change Password**: weka password hiyo kwenye **Current password**, chagua na uthibitishe password mpya, kisha ingia tena kwa mpya. Password mpya haitahifadhiwa kwenye README hii. Sharti hili linahusu akaunti za watumishi zilizowekewa mabadiliko ya lazima; wanafunzi hubadilisha password kwa hiari.

## 5. Jaribu clearance

1. Ingia kama mwanafunzi mpya, kwa mfano `IRDP/BTCCD/MA25/0006`.
2. Fungua **Clearance Fee** na bonyeza **Request control number**. Ada ya mwanzo ni **TSh 10,000**.
3. Log out, ingia kama Finance (`FIN001`) na fungua **Clearance Fee Payments**. Finance anaweza kuweka au kubadilisha ada kwa cycle husika; kiwango kipya kinatumika kwa maombi mapya tu. Fungua **Review payment**, weka control number na bonyeza **Send control number**.
4. Ingia tena kama mwanafunzi. Lipa kwa control number hiyo, kisha kwenye **Clearance Fee** pakia receipt moja ya PDF/JPG/PNG, hadi 5 MB.
5. Ingia kama Finance, fungua receipt, hakiki malipo kwenye kumbukumbu za malipo, tia alama ya uthibitisho na bonyeza **Approve payment**.
6. Mwanafunzi sasa anaweza kubonyeza **Start Clearance**, kuchagua academic cycle iliyofunguliwa na admin na kutuma ombi. Fungua **My Clearance**; ofisi zote 11 zinaanza **PENDING**.
7. Log out, ingia kama officer na bonyeza **Review** kwenye mwanafunzi huyo. Ofisi nyingine zina **Approve** au **Reject**. Finance akikuta hakuna deni jingine anachagua **Approve — no other debt**; akikuta deni jingine anaweka control number tofauti na kuhakiki receipt yake.
8. Rudia kwa ofisi nyingine kwa mpangilio wowote. Ofisi zote 11 zikiidhinisha, mwanafunzi anaona **COMPLETED** na **Download Clearance Transcript** kwenye Dashboard.

**Namba moja ya usajili inaanzisha clearance mara moja tu.** Ada ya kuanza inawahusu ambao hawajawahi kuanza clearance. Walioanza tayari na waliomaliza wanaendelea kutumia clearance na transcript zao bila kuombwa ada nyingine, hata academic cycle ikibadilika. Ombi lililofutwa halifungui nafasi ya kuanzisha jingine; wasiliana na Admin. Admin anafungua kipindi cha clearance; Finance and Accounting ndiye anasimamia ada, control number na uthibitisho wa malipo.

Ili kufungua akaunti mbili kwa wakati mmoja, tumia browser tofauti au dirisha la Incognito/Private kwa akaunti ya pili. Tabs za kawaida katika browser moja zinatumia login ileile.

### Ofisi ikikataa

Officer wa ofisi husika anaweka sababu na maelekezo ya marekebisho. Mwanafunzi anafungua **My Clearance**, anarekebisha kilichoombwa, kisha anatuma maelezo na ushahidi kupitia **Submit evidence to office**. Officer huyo anahakiki na kuidhinisha marekebisho. Ofisi nyingine zinaendelea kufanya review wakati unasubiri.

### Finance na receipt

Katika hatua ya 11, Finance anakagua **madeni mengine**, tofauti na ada ya kuanza clearance. Bila deni jingine, anachagua **Approve — no other debt** bila kuomba malipo mengine. Deni jingine likiwepo, Finance anaweka **control number** ya tarakimu 6–30 na kuchagua **Send control number**. Hatua ya Finance inabaki **PENDING**, ikisubiri malipo. Mwanafunzi analipa kwa namba hiyo na kupakia receipt moja ya PDF/JPG/PNG, hadi 5 MB. Finance anafungua receipt, anahakiki malipo, kisha anachagua **Approve**. **Update control number** inahitaji receipt mpya na inahifadhi history. Finance hana kitufe cha Reject. Kupakia receipt peke yake hakukamilishi clearance; approvals zote 11 zinahitajika.

Receipt ikihitaji kusahihishwa kabla ya Finance ku-approve, mwanafunzi anaweza kufungua **View or replace payment receipt** na kuchagua **Replace payment receipt**. Receipt ya zamani inabaki kwenye history, na Finance inahakiki receipt mpya.

### Session kuisha

Mfumo unaonyesha onyo dakika 1 kabla ya session kuisha. Bonyeza **Continue session / Endelea** ili kuendelea na form yako. Session inaisha baada ya dakika 30 bila shughuli, au saa 8 tangu login; ukomo wa saa 8 unahitaji kuingia tena.

## 6. Ukikwama

| Tatizo | Cha kufanya |
|---|---|
| Invalid username or password | Nakili registration number yote; hakikisha password ina herufi kubwa kama kwenye jedwali, au tumia password uliyobadilisha. |
| Clearance is currently closed | Ingia kama admin na ufungue **Clearance Period** kwa academic cycle unayotumia. |
| Continue Clearance inaonekana badala ya Start Clearance | Ombi la cycle hiyo tayari lipo. Endelea kupitia **My Clearance**. |
| Start Clearance haijafunguka | Fungua **Clearance Fee**, omba control number na pakia receipt. Subiri Finance aidhinishe malipo. |
| Finance haoni ombi la ada kwenye Pending Clearance | Fungua **Clearance Fee Payments**; maombi haya yapo kabla ya clearance kuanza. |
| Officer haoni mwanafunzi | Hakikisha mwanafunzi ameanza clearance na unatumia akaunti ya ofisi husika. Review iliyokwisha kuidhinishwa huondoka kwenye pending tasks. |
| Transcript haionekani | Angalia **My Clearance**; ofisi zote 11 lazima ziwe APPROVED, na Finance awe amethibitisha hakuna deni jingine au amehakiki receipt ya deni hilo. |

## Verification kwa developer

Endesha ukiwa kwenye folder ya mradi:

```powershell
& 'C:/xampp1/php/php.exe' tests/documented_demo_accounts_test.php
# Pima login, sharti la password la FMS na Finance kupitia Apache:
& 'C:/xampp1/php/php.exe' tests/documented_demo_accounts_test.php FMS001 FIN001
```

Test hii inasoma database ya sasa bila kubadilisha password: inahakiki akaunti zote 34, email zilizoandikwa, majukumu ya FMS/EPM/Finance na schema. Password zilizoandikwa zinahakikiwa dhidi ya hash; alama **—** huripotiwa kuwa password haijachapishwa na haitumiki kwenye jaribio la login. Password iliyoandikwa ikibadilishwa, test itaripoti tofauti bila kuirejesha. Ukiongeza username baada ya jina la test, inapima login na dashboard kupitia Apache; kwa mtumishi mwenye sharti la kubadilisha password, inahakiki ukurasa wa Change Password na kuzuiwa kwa dashboard. Vipimo vya HTTP huhifadhi rekodi za kawaida za login; tumia akaunti chache kwa mara ili kuheshimu ukomo wa majaribio ya kuingia.
