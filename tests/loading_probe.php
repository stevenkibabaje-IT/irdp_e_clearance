<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/installer.php';
foreach([false,true] as $withDatabase){$start=microtime(true);$db=db_connect($withDatabase);echo 'Connect '.($withDatabase?'database':'server').': '.round((microtime(true)-$start)*1000).' ms'.PHP_EOL;}
$start=microtime(true);application_database();echo 'Runtime database startup: '.round((microtime(true)-$start)*1000).' ms'.PHP_EOL;
$page=$argv[1]??'http://127.0.0.1/Irdp_e_clearance/';
for($i=0;$i<5;$i++){$curl=curl_init($page);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);$body=curl_exec($curl);if($body===false){throw new RuntimeException(curl_error($curl));}echo 'Page '.curl_getinfo($curl,CURLINFO_HTTP_CODE).': '.round(curl_getinfo($curl,CURLINFO_TOTAL_TIME)*1000).' ms'.PHP_EOL;curl_close($curl);}
