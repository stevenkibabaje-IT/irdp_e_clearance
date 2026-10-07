<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/functions.php';
function assert_policy(bool $condition,string $message): void {if(!$condition){throw new RuntimeException($message);}}
function deny_password(string $password,?string $confirmation=null): void {
    try {password_policy($password,$confirmation??$password);} catch(ValidationException $e) {return;}
    throw new RuntimeException('An invalid password was accepted.');
}
foreach(['Abc1234',str_repeat('x',65),'password123456789','123456789012345','abcabcabcabcabc',str_repeat(' ',15),"Meadow ocean\nlantern 67"] as $bad){deny_password($bad);}
deny_password('Meadow ocean lantern 67','Meadow ocean lantern 68');
foreach(['Abc12345','Meadow ocean lantern 67','Zebra cloud #29!',str_repeat('河',63).'灯',str_repeat('🌳',63).'🌊'] as $good){
    assert_policy(password_policy($good,$good)===$good,'Valid passphrase or Unicode boundary rejected.');
    $hash=hash_new_password($good);
    assert_policy(password_verify($good,$hash),'New password hash cannot be verified.');
    assert_policy(!password_verify(mb_substr($good,0,-1,'UTF-8').'X',$hash),'Password suffix was truncated by hashing.');
}
assert_policy(password_verify('ExistingLongPassword',password_hash('ExistingLongPassword',PASSWORD_DEFAULT)),'Legacy hash compatibility lost.');
$_POST=[];
ob_start();form_fields(['password'=>['label'=>'New password','type'=>'password','maxlength'=>64,'autocomplete'=>'new-password']],['password'=>'Choose a stronger password.']);$html=ob_get_clean();
$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
$input=$xpath->query('//input[@name="password"]')->item(0);
assert_policy($input && $input->getAttribute('aria-invalid')==='true','Invalid field not announced.');
foreach(explode(' ',$input->getAttribute('aria-describedby')) as $id){assert_policy($xpath->query('//*[@id="'.$id.'"]')->length===1,'Help or error reference missing.');}
assert_policy(!$input->hasAttribute('maxlength'),'Unicode password is silently truncated in the form.');
assert_policy($xpath->query('//label[@for="password"]')->length===1,'Field has no accessible label.');
echo "PASS: password lengths, common/repeated password rejection, Unicode hashing, legacy hashes and accessible form errors\n";
