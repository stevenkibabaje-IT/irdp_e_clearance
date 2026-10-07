<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/functions.php';

foreach([
    'default namespace'=>'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>full_name</t></is></c></row></sheetData></worksheet>',
    'prefixed namespace'=>'<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData><x:row r="1"><x:c r="A1" t="inlineStr"><x:is><x:t>full_name</x:t></x:is></x:c></x:row></x:sheetData></x:worksheet>',
    'empty shared strings'=>'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>',
] as $name=>$xml) {
    if(!xml_document($xml) instanceof SimpleXMLElement) {throw new RuntimeException('Valid XML rejected: '.$name);}
    echo 'PASS: '.$name.PHP_EOL;
}
foreach([
    'malformed XML'=>'<worksheet><sheetData></worksheet>',
    'DOCTYPE'=>'<!DOCTYPE worksheet [<!ENTITY test "unsafe">]><worksheet/>',
    'entity declaration'=>'<!ENTITY test "unsafe"><worksheet/>',
    'oversized XML'=>str_repeat(' ',8388609),
] as $name=>$xml) {
    $rejected=false;
    try {xml_document($xml);} catch(RuntimeException $e) {$rejected=true;}
    if(!$rejected) {throw new RuntimeException('Invalid XML accepted: '.$name);}
    echo 'PASS: rejected '.$name.PHP_EOL;
}
libxml_use_internal_errors(false);
xml_document('<sst/>');
if(libxml_use_internal_errors()!==false) {throw new RuntimeException('XML parser changed libxml error handling.');}
echo 'PASS: libxml error handling preserved'.PHP_EOL;

foreach([
    'duplicate cell'=>'<c r="A1" t="inlineStr"><is><t>first</t></is></c><c r="A1" t="inlineStr"><is><t>second</t></is></c>',
    'zero row'=>'<c r="A0" t="inlineStr"><is><t>value</t></is></c>',
    'extra column'=>'<c r="G1" t="inlineStr"><is><t>value</t></is></c>',
] as $name=>$cells) {
    $archiveName=bin2hex(random_bytes(24)).'.zip';
    $archivePath=private_path('imports',$archiveName);
    try {
        $archive=new PharData($archivePath);
        $archive['xl/worksheets/sheet1.xml']='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$cells.'</row></sheetData></worksheet>';
        unset($archive);
        $rejected=false;try{xlsx_rows($archivePath);}catch(RuntimeException $e){$rejected=true;}
        if(!$rejected){throw new RuntimeException('Invalid workbook accepted: '.$name);}
        echo 'PASS: rejected workbook '.$name.PHP_EOL;
    } finally {
        unset($archive);
        private_delete('imports',$archiveName);
    }
}
