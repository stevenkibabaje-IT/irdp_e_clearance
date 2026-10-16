<?php
declare(strict_types=1);

/** Short guidance follows the rendered route; no student data is exposed here. */
function page_help_context(): array
{
    $path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $role = current_user()['role'] ?? '';
    $route = basename(dirname($path)) . '/' . basename($path);
    $guides = [
        'auth/login.php' => ['Kuingia kwenye mfumo', [
            'Mwanafunzi: andika namba yote ya usajili, pamoja na alama za /. Mtumishi: tumia username uliyopewa.',
            'Weka nenosiri lako, kisha bonyeza Login. Icon ya jicho kwenye nenosiri hukusaidia kuona ulichoandika.',
            'Umesahau nenosiri? Tumia Forgot Password kutuma ombi la kurejesha akaunti.',
        ]],
        'auth/forgot.php' => ['Kurejesha akaunti', [
            'Mwanafunzi: weka email iliyohifadhiwa kwenye akaunti yako, kisha bonyeza Send OTP.',
            'Angalia inbox na spam. Weka OTP ya tarakimu sita kwenye kivinjari hiki ndani ya dakika 10.',
            'Kama huwezi kufungua email yako au unatumia akaunti ya mtumishi, wasiliana na msaada kwa uhakiki wa utambulisho na urejeshaji kupitia Admin.',
        ]],
        'auth/verify_otp.php' => ['Kuthibitisha email', [
            'Weka OTP ya tarakimu sita uliyopokea kwenye email, kisha bonyeza Verify OTP.',
            'Code ina muda wa dakika 10 na majaribio matano. Tumia kivinjari kilekile ulichoomba OTP.',
            'Subiri sekunde 60 kabla ya Resend OTP. Code mpya ikitumwa, code ya awali haitatumika tena.',
        ]],
        'auth/reset.php' => ['Kuweka nenosiri jipya', [
            'Baada ya Verify OTP, andika na urudie nenosiri jipya ndani ya dakika 10, kisha bonyeza Reset password.',
            'Ukipewa reset code na Admin baada ya uhakiki wa utambulisho, tumia link ya Reset Password uliyopewa. Code hiyo ina muda wa dakika 30.',
            'Baada ya kuhifadhi, ingia tena kwa nenosiri jipya. Code ikiisha muda, omba nyingine au wasiliana na msaada.',
        ]],
        'auth/change_password.php' => ['Kubadilisha nenosiri', [
            'Weka nenosiri lako la sasa, kisha andika na urudie nenosiri jipya.',
            'Tumia maneno au herufi ambazo si rahisi kukisiwa; fuata kiwango kinachoonyeshwa kwenye form.',
            'Ukihifadhi, utaingia tena kwa nenosiri jipya. Change Password na Log out zinapatikana kwenye profile ya juu kulia.',
        ]],
        'student/dashboard.php' => ['Dashboard yako', [
            'Angalia kama clearance iko OPEN na academic cycle inayohusika.',
            'Kama hujaanza, fungua Clearance Fee. Ikiwa tayari umeanza, tumia Continue Clearance au My Clearance.',
            'Progress circle na Office Status ziko kwenye panel moja. Fungua Correction / Upload Evidence pale ofisi inapoomba hatua.',
            'Picha, My Profile, Change Password na Log out vinapatikana kwenye profile ya juu kulia. Taarifa za maamuzi ziko kwenye kengele.',
        ]],
        'student/clearance_fee.php' => ['Kulipa ada ya kuanza clearance', [
            'Bonyeza Request control number, kisha subiri Finance akupe namba na kiasi cha kulipa.',
            'Lipa kwa control number hiyo. Pakia receipt moja ya PDF, JPG au PNG isiyozidi 5 MB.',
            'Subiri malipo yawe APPROVED. Kupakia receipt pekee hakufungui Start Clearance.',
            'Clearance ikiwa OPEN na ada imeidhinishwa, endelea kwenye Start Clearance.',
        ]],
        'student/start.php' => ['Kuanza clearance', [
            'Hakikisha kipindi cha clearance kiko OPEN na ada ya kuanza imeidhinishwa na Finance.',
            'Chagua academic cycle iliyofunguliwa, hakiki taarifa, kisha tuma ombi mara moja.',
            'Namba moja ya usajili inaanzisha clearance moja tu. Academic cycle mpya haifungui ombi la pili; endelea kupitia My Clearance.',
            'Baada ya kutuma, ofisi zote 11 zinaweza kufanya review kwa kujitegemea. Fuatilia kupitia My Clearance.',
        ]],
        'student/status.php' => ['Kuelewa hali ya ofisi', [
            'Chagua ofisi kwenye Office Reviews kufungua maelezo, historia, malipo au sehemu ya kupakia ushahidi.',
            'Pending: ofisi inasubiri review. In review: ombi linakaguliwa. Approved: ofisi imeidhinisha.',
            'Rejected / Action required: soma sababu na maelekezo ya ofisi, rekebisha kisha tuma maelezo au ushahidi.',
            'Finance ikitoa control number ya deni, lipa na pakia receipt kwa namba hiyo. Kupakia receipt hakumaanishi approval.',
            'Ofisi zote 11 zikiidhinisha, transcript itapatikana kupitia Dashboard.',
        ]],
        'student/resubmit.php' => ['Kutuma marekebisho', [
            'Soma sababu ya kukataliwa na Corrective instructions za ofisi hii.',
            'Eleza ulichorekebisha. Unaweza kuongeza hadi faili 5 za PDF, JPG au PNG, kila moja isizidi 5 MB.',
            'Bonyeza Submit evidence to office. Subiri officer ahakiki; approvals za ofisi nyingine zinahifadhiwa.',
            'Kwa malipo ya Finance, tumia control number na receipt inayohusiana nayo.',
        ]],
        'student/profile.php' => ['Kusimamia profile', [
            'Hapa unaweza kuona taarifa zako, kubadilisha picha, kuweka email ya notifications na kuhifadhi namba yako ya simu.',
            'Kwenye Phone number, andika namba kama 0712345678 au +255712345678 kisha bonyeza Save phone.',
            'Mabadiliko ya email yanahitaji nenosiri lako la sasa. Picha iwe JPG au PNG, hadi 5 MB.',
            'Chagua picha uone preview, kisha bonyeza Save picture. Preview pekee haihifadhi picha.',
            'Kubadilisha nenosiri au kutoka, fungua profile ya juu kulia.',
        ]],
        'student/notifications.php' => ['Taarifa za akaunti', [
            'Soma maamuzi na taarifa zinazohusu akaunti yako. Kengele ya juu inaonyesha taarifa ambazo hujasoma.',
            'Tumia Mark all as read baada ya kuzisoma. Kwa marekebisho au malipo, fungua My Clearance au kazi husika.',
        ]],
        'officer/dashboard.php' => ['Kazi za ofisi yako', [
            'Summary inaonyesha kazi zinazosubiri review, ushahidi uliorudishwa, approvals za leo na marekebisho yanayosubiri mwanafunzi.',
            'Bonyeza Pending reviews au Evidence returned kuchuja kazi ulizopangiwa, kisha bonyeza Review.',
            'Hakiki mahitaji ya ofisi yako na ushahidi wa mwanafunzi kabla ya kufanya uamuzi.',
            'Kila ofisi hufanya review kwa kujitegemea. Maombi yaliyotumwa upya yanahitaji ukaguzi wa ushahidi mpya.',
        ]],
        'officer/review.php' => ['Kuhakiki clearance', [
            'Hakiki taarifa za mwanafunzi, mahitaji ya ofisi na ushahidi uliowasilishwa.',
            'Approve baada ya kuthibitisha mahitaji. Reject inahitaji sababu na maelekezo ya marekebisho.',
            'Finance: kama hakuna deni lingine tumia Approve — no other debt; deni likiwepo toa control number na uhakiki receipt. Finance haitumii Reject.',
        ]],
        'officer/clearance_fees.php' => ['Ada za kuanza clearance', [
            'Weka kiwango cha ada cha cycle husika, kisha fungua Review payment kwenye ombi.',
            'Toa control number, subiri receipt na hakiki malipo kabla ya kuidhinisha.',
            'Approval ya ada inamruhusu mwanafunzi kuanza clearance wakati kipindi kiko OPEN.',
        ]],
        'officer/clearance_fee_review.php' => ['Kuhakiki malipo ya ada', [
            'Toa au hakiki control number ya ombi hili. Kuibadilisha kunahitaji receipt mpya.',
            'Fungua receipt na thibitisha kiasi pamoja na control number kabla ya kuchagua Approve payment.',
            'Receipt za awali zinahifadhiwa kwenye history.',
        ]],
        'admin/clearance_period.php' => ['Kusimamia kipindi cha clearance', [
            'Chagua academic cycle iliyopo au tengeneza cycle mpya kwa muundo unaoonyeshwa.',
            'Chagua kufungua, kufunga au kupanga muda. Kwa ratiba, muda wa kufunga ufuate muda wa kufungua.',
            'Hakiki maamuzi kabla ya kuhifadhi; kipindi hiki kinaamua upatikanaji wa huduma za clearance kwa wanafunzi.',
        ]],
        'admin/users.php' => ['Kusimamia akaunti', [
            'Chagua role sahihi, kisha jaza taarifa na nenosiri la awali.',
            'Mwanafunzi anahitaji department, programme na academic cycle. Programme ilingane na namba yake ya usajili.',
            'Officer anahitaji office; Head of Department anahitaji department husika pia.',
        ]],
        'admin/departments.php' => ['Kusimamia departments', [
            'Tumia code na jina rasmi la department. Code iwe na herufi kubwa kulingana na form.',
            'Orodha inaonyesha departments, idadi ya wanafunzi na hali yake.',
            'Departmental reviewer lazima awe na department inayolingana na ya wanafunzi anaowahudumia.',
        ]],
        'admin/recovery.php' => ['Maombi ya kurejesha nenosiri', [
            'Chagua ombi, hakiki utambulisho wa mwenye akaunti na uweke kumbukumbu inayohitajika.',
            'Approve huruhusu mwenye akaunti kuweka nenosiri jipya; Reject inahitaji sababu.',
            'Credential ina muda maalumu. Usitume nenosiri la mtumiaji kupitia mawasiliano yasiyothibitishwa.',
        ]],
        'admin/reports.php' => ['Kutengeneza ripoti', [
            'Chagua aina ya ripoti na kipindi unachohitaji.',
            'Kwa custom dates, weka tarehe ya kuanzia na ya mwisho; tarehe ya mwisho isiwe kabla ya ya kuanzia.',
            'Bonyeza kitufe cha kutengeneza ripoti ili kufungua PDF.',
        ]],
        'admin/import.php' => ['Kuingiza wanafunzi kwa faili', [
            'Pakua template ya CSV au XLSX, kisha jaza codes za programme na department zilizopo.',
            'Pakia faili lisilozidi 5 MB uone preview. Soma makosa na hakiki taarifa kabla ya kuingiza rows sahihi.',
            'Akaunti zilizoingizwa zinahitaji hatua ya activation iliyoelezwa kwenye ukurasa.',
        ]],
    ];
    if (isset($guides[$route])) { return $guides[$route]; }
    if ($role === 'ADMIN') {
        return ['Kusimamia mfumo', ['Chagua huduma unayohitaji kwenye menu ya kushoto; kwenye simu, fungua menu ya juu.',
            'Dashboard ina muhtasari. Clearance Monitoring ina maombi, na Clearance Period inadhibiti cycle iliyofunguliwa.',
            'Profile ya juu kulia ina Change Password na Log out. Kengele ina taarifa za akaunti.']];
    }
    if (in_array($role, ['OFFICER', 'SUPERVISOR'], true)) { return $guides['officer/dashboard.php']; }
    if ($role === 'STUDENT') { return $guides['student/dashboard.php']; }
    return ['Karibu kwenye e-Clearance', ['Bonyeza Login / Ingia kuingia kwenye akaunti yako.',
        'Mwanafunzi atumie namba yote ya usajili na nenosiri; mtumishi atumie username aliyopewa.',
        'Baada ya kuingia, fuata ada ya kuanza clearance, review za ofisi na upatikanaji wa transcript.',
        'Ukiwa hujaelewa hatua, soma maswali ya kawaida au piga simu kupitia Mawasiliano.']];
}
