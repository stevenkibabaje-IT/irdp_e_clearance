<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__ . '/../includes/functions.php';
function expect_field_error(callable $operation, string $field): void {
    try { $operation(); }
    catch (ValidationException $error) {
        if (isset($error->errors[$field])) { return; }
        throw new RuntimeException('Validation error was not associated with ' . $field . '.');
    }
    throw new RuntimeException('Invalid value was accepted for ' . $field . '.');
}
expect_field_error(fn()=>text_input(['full_name'=>'   '], 'full_name', 160), 'full_name');
expect_field_error(fn()=>person_name('12345'), 'full_name');
expect_field_error(fn()=>person_name("\u{0301}"), 'full_name');
expect_field_error(fn()=>text_input(['name'=>"\u{00A0}\u{2003}"], 'name', 180), 'name');
if (text_input(['name'=>"\u{00A0}Anne\u{00A0}"], 'name', 180) !== 'Anne') {
    throw new RuntimeException('Unicode surrounding spaces were not normalized.');
}
expect_field_error(fn()=>email_value('invalid-email'), 'email');
expect_field_error(fn()=>password_policy('Abc1234', 'Abc1234'), 'password');
expect_field_error(fn()=>password_policy('Garden ocean breeze 82', 'Mismatch'), 'confirm_password');
expect_field_error(fn()=>validate_profile_upload([]), 'picture');
expect_field_error(fn()=>store_evidence_upload([]), 'evidence');
expect_field_error(fn()=>upload_file_list(['name'=>array_fill(0,6,'document.pdf')]), 'evidence');
expect_field_error(fn()=>period_datetime(['opens_at'=>'2026-02-30T12:00'], 'opens_at'), 'opens_at');
expect_field_error(fn()=>period_datetime(['opens_at'=>'0000-01-01T12:00'], 'opens_at'), 'opens_at');
expect_field_error(fn()=>validate_review_details([['amount','Amount','number']], ['amount'=>'junk']), 'amount');
expect_field_error(fn()=>validate_review_details([['item','Item','asset']], ['item'=>'UNKNOWN']), 'item');
if (person_name("  Anne O’Neil-Said  ") !== "Anne O’Neil-Said"
    || registration_number(' IRDP/ODICT/MA25/0001 ') !== 'IRDP/ODICT/MA25/0001'
    || email_value(' anne@example.org ') !== 'anne@example.org'
    || validate_review_details([['amount','Amount','number']], ['amount'=>'12.50']) !== ['amount'=>'12.50']) {
    throw new RuntimeException('Valid input was changed or rejected.');
}
echo "PASS: field-level errors, required text, names, email, passwords, uploads, dates and clearance amounts/options\n";

foreach (["Valid\0", "\0Valid", "Valid\v", "Bad\x01text", "\xC3\x28", ['nested'], true] as $bad) {
    expect_field_error(fn()=>text_input(['name'=>$bad], 'name', 180), 'name');
}
expect_field_error(fn()=>text_input(['name'=>str_repeat('界',181)], 'name', 180), 'name');
if (text_input(['notes'=>" Line one\r\nLine two\tend "], 'notes', 100) !== "Line one\r\nLine two\tend"
    || text_input([], 'optional', 10, false) !== ''
    || text_input(['name'=>str_repeat('界',180)], 'name', 180) !== str_repeat('界',180)) {
    throw new RuntimeException('Valid multiline/Unicode/optional input rejected.');
}
foreach ([null, [], true, false, 1.0, 0, -1, '0', '01', '1.0', '1e3', '2147483648', '999999999999999999999', '1junk'] as $bad) {
    expect_field_error(fn()=>positive_id($bad, 'record'), 'record');
}
if (positive_id('2147483647') !== 2147483647 || positive_id(1) !== 1 || optional_id('', 'record') !== null) {
    throw new RuntimeException('Valid record ID boundaries rejected.');
}
foreach (["2026-10-07\0", '2026-02-30', '2026-2-03', '2026-10-07extra', '0000-01-01', ''] as $bad) {
    if (valid_date($bad)) {throw new RuntimeException('Invalid date accepted.');}
}
if (!valid_date('2024-02-29') || valid_date('2026-02-29')) {throw new RuntimeException('Leap year validation failed.');}
expect_field_error(fn()=>academic_cycle('2026/2028'), 'academic_year');
expect_field_error(fn()=>registration_number('wrong', 'registration_number'), 'registration_number');
expect_field_error(fn()=>person_name("Anne\0"), 'full_name');
expect_field_error(fn()=>person_name('Anne <script>'), 'full_name');
expect_field_error(fn()=>person_name(str_repeat('A',161)), 'full_name');
expect_field_error(fn()=>email_value("anne@example.org\nOther"), 'email');
expect_field_error(fn()=>email_value("anne@example.org\0"), 'email');
foreach ([[], '', str_repeat('a',4097), "Garden\0ocean", "\xC3\x28"] as $bad) {
    expect_field_error(fn()=>existing_password_input($bad), 'password');
}
if (existing_password_input(str_repeat('LongLegacyPassword',10)) !== str_repeat('LongLegacyPassword',10)) {
    throw new RuntimeException('Legacy long password validation changed.');
}
foreach (['-1', '+1', '1e3', 'NaN', 'INF', '1,000', '1.234', '.50', '1000000000000', str_repeat('9',400), "0\0", [], true] as $bad) {
    expect_field_error(fn()=>validate_review_details([['amount','Amount','number']], ['amount'=>$bad]), 'amount');
}
foreach (['0', '0.01', '12.50', '999999999999.99'] as $good) {
    if (clearance_amount($good, 'amount', 'Amount') !== $good) {throw new RuntimeException('Valid money boundary rejected.');}
}
expect_field_error(fn()=>choice_input('UNKNOWN', ['AVAILABLE','MISSING'], 'asset'), 'asset');
for ($step=1;$step<=11;$step++) {
    if (liabilities_clear([], $step)) {throw new RuntimeException('Missing approval details treated as cleared.');}
}
if (liabilities_clear(['decision'=>'CLEARED','debt'=>'bad','recovered'=>'0'],11)
    || liabilities_clear(['decision'=>'CLEARED'],7)
    || !liabilities_clear(['decision'=>'CLEARED','debt'=>'0','recovered'=>'0'],11)) {
    throw new RuntimeException('Finance or departmental liability validation failed.');
}
foreach ([
    ['name'=>'file.pdf','error'=>0],
    ['name'=>['file.pdf']],
    ['name'=>[['nested.pdf']],'tmp_name'=>['temp'],'error'=>[0],'size'=>[1],'type'=>['application/pdf']],
    ['name'=>['file.pdf'],'tmp_name'=>['temp'],'error'=>['0'],'size'=>[1],'type'=>['application/pdf']],
    ['name'=>['file.pdf'],'tmp_name'=>['temp'],'error'=>[0],'size'=>[-1],'type'=>['application/pdf']],
] as $bad) {
    expect_field_error(fn()=>upload_file_list($bad), 'evidence');
}
if (upload_file_list([]) !== [] || upload_file_list(['name'=>[''],'tmp_name'=>[''],'error'=>[UPLOAD_ERR_NO_FILE],'size'=>[0],'type'=>['']]) !== []) {
    throw new RuntimeException('Optional evidence upload rejected.');
}
foreach (["bad\0.pdf", "bad\r\n.pdf", str_repeat('a',181).'.pdf', "\xC3\x28.pdf", '.', '..', ''] as $bad) {
    $denied=false;try{upload_filename($bad);}catch(RuntimeException $e){$denied=true;}
    if(!$denied){throw new RuntimeException('Invalid upload filename accepted.');}
}
if (upload_filename('C:\\fakepath\\receipt.pdf') !== 'receipt.pdf'
    || upload_filename('/folder/risiti nzuri.pdf') !== 'risiti nzuri.pdf') {
    throw new RuntimeException('Client upload directory was not removed.');
}
expect_field_error(fn()=>parse_import_file([]), 'file');
echo "PASS: hostile input types, control bytes, Unicode boundaries, IDs/dates, money bounds, complete liabilities and upload shapes\n";

foreach (['', 'abc123', '12345', '12e6', '9912-345678', str_repeat('9',31)] as $invalid) {
    expect_field_error(fn()=>finance_control_number($invalid), 'control_number');
}
if (finance_control_number(' 001234567890 ') !== '001234567890'
    || finance_details_clear(['control_number'=>'991234567890','payment_status'=>'AWAITING_REVIEW'])
    || finance_details_clear(['control_number'=>'991234567890','payment_status'=>'APPROVED'])
    || finance_details_clear(['control_number'=>'991234567890','payment_status'=>'APPROVED','receipt_control_number'=>'991234567891','receipt_id'=>1,'receipt_cycle'=>2])
    || !finance_details_clear(['control_number'=>'991234567890','payment_status'=>'APPROVED','receipt_control_number'=>'991234567890','receipt_id'=>1,'receipt_cycle'=>2])) {
    throw new RuntimeException('Finance control number or receipt association validation failed.');
}
echo "PASS: Finance control numbers, leading zeroes, mandatory receipts and matching payment associations\n";
