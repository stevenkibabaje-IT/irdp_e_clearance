<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_role('ADMIN');$format=$_GET['format']??'csv';$rows=student_template_rows();
if($format==='csv'){header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="IRDP-Students-Template.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");foreach($rows as $row){fputcsv($out,$row,',','"','');}fclose($out);exit;}
if($format!=='xlsx'){http_response_code(400);exit('Invalid template format.');}
$name=bin2hex(random_bytes(24)).'.zip';$path=private_path('imports',$name);
try{
    $zip=new PharData($path);$zip['[Content_Types].xml']='<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $zip['_rels/.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $zip['xl/workbook.xml']='<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $zip['xl/_rels/workbook.xml.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $xml='<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';foreach($rows as $i=>$row){$xml.='<row r="'.($i+1).'">';foreach($row as $col=>$value){$xml.='<c r="'.chr(65+$col).($i+1).'" t="inlineStr"><is><t>'.htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$xml.='</row>';}$xml.='</sheetData></worksheet>';$zip['xl/worksheets/sheet1.xml']=$xml;unset($zip);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="IRDP-Students-Template.xlsx"');readfile($path);
}finally{unset($zip);private_delete('imports',$name);}
