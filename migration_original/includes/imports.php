<?php
declare(strict_types=1);

/** Read student import files, validate a preview and commit accepted account rows. */
const STUDENT_IMPORT_HEADERS = ['full_name','registration_number','programme','department','academic_cycle','email'];
function validate_student_row(PDO $pdo,array $row): array {
    $name=person_name(text_input($row,'full_name',160));
    $reg=registration_number(text_input($row,'registration_number',80), 'registration_number');
    $programme=text_input($row,'programme',40);
    $department=text_input($row,'department',30);
    $cycle=existing_academic_cycle($pdo,text_input($row,'academic_cycle',20),'academic_cycle');
    $email=email_value(text_input($row,'email',160,false));
    unique_account_email($pdo,$email);
    $s=$pdo->prepare('SELECT id FROM departments WHERE code=? AND active=1');
    $s->execute([$department]);
    $departmentId=$s->fetchColumn();
    if(!$departmentId) {
        throw new ValidationException(['department'=>'Unknown or inactive department code.']);
    }
    $s=$pdo->prepare('SELECT id FROM programmes WHERE code=? AND active=1');
    $s->execute([$programme]);
    if(!$s->fetchColumn()) {
        throw new ValidationException(['programme'=>'Unknown or inactive programme code.']);
    }
    if(explode('/',$reg)[1]!==$programme) {
        throw new ValidationException(['programme'=>'Programme must match the registration number.']);
    }
    $s=$pdo->prepare('SELECT id FROM users WHERE username=? UNION ALL SELECT user_id FROM students WHERE registration_number=?');
    $s->execute([$reg,$reg]);
    if($s->fetchColumn()) {
        throw new ValidationException(['registration_number'=>'An account or Student with this registration number already exists.']);
    }
    return ['full_name'=>$name,'registration_number'=>$reg,'programme'=>$programme,'department'=>$department,'department_id'=>(int)$departmentId,'academic_cycle'=>$cycle,'email'=>$email];
}
function create_student_account(PDO $pdo,array $row): int {
    $role=(int)$pdo->query('SELECT id FROM roles WHERE name="STUDENT"')->fetchColumn();
    // An unpredictable inaccessible initial secret, followed by verified individual activation.
    $secret=bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO users(username,password_hash,full_name,email,role_id,force_password_change) VALUES (?,?,?,?,?,0)')->execute([$row['registration_number'],hash_new_password($secret),$row['full_name'],$row['email'],$role]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO students(user_id,registration_number,programme,year_of_study,department_id) VALUES (?,?,?,?,?)')->execute([$id,$row['registration_number'],$row['programme'],$row['academic_cycle'],$row['department_id']]);
    return $id;
}
function xml_document(string $xml): SimpleXMLElement {
    if(strlen($xml)>8388608 || stripos($xml,'<!DOCTYPE')!==false || stripos($xml,'<!ENTITY')!==false) {
        throw new RuntimeException('Unsafe or oversized spreadsheet XML.');
    }
    $previous=libxml_use_internal_errors(true);
    $result=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    // Valid SimpleXML objects can be falsey for prefixed or empty XML roots.
    // Only the boolean false return value means parsing actually failed.
    if($result === false) {
        throw new RuntimeException('Invalid spreadsheet XML.');
    }
    return $result;
}
function xlsx_rows(string $path): array {
    $zipName=bin2hex(random_bytes(24)).'.zip';
    $zipPath=private_path('imports',$zipName);
    if(!copy($path,$zipPath)) {
        throw new RuntimeException('Spreadsheet could not be opened.');
    }
    try {
        $archive=new PharData($zipPath);
        $total=0;
        $count=0;
        foreach(new RecursiveIteratorIterator($archive) as $entry) {
            $total+=$entry->getSize();
            $count++;
            if($total>20971520 || $entry->getSize()>8388608 || $count>100 || str_ends_with(strtolower($entry->getFilename()),'.bin')) {
                throw new RuntimeException('Spreadsheet is too large or contains unsupported binary/macros.');
            }
        }
        if(!isset($archive['xl/worksheets/sheet1.xml'])) {
            throw new RuntimeException('Use a workbook with its import data in the first worksheet.');
        }
        $strings=[];
        if(isset($archive['xl/sharedStrings.xml'])) {
            $xml=xml_document($archive['xl/sharedStrings.xml']->getContent());
            foreach($xml->xpath('//*[local-name()="si"]') as $item) {
                $text='';
                foreach($item->xpath('.//*[local-name()="t"]') as $t) {
                    $text.=(string)$t;
                }
                if(strlen($text)>1024) {
                    throw new RuntimeException('Spreadsheet cell exceeds the limit.');
                }
                $strings[]=$text;
                if(count($strings)>10000) {
                    throw new RuntimeException('Too many shared strings.');
                }
            }
        }
        $xml=xml_document($archive['xl/worksheets/sheet1.xml']->getContent());
        $rows=[];
        foreach($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $record) {
            if(count($rows)>IMPORT_MAX_ROWS) {
                throw new RuntimeException('Import at most '.IMPORT_MAX_ROWS.' rows.');
            }
            $row=array_fill(0,6,'');
            $formula=false;
            $seenColumns=[];
            foreach($record->xpath('./*[local-name()="c"]') as $cell) {
                $ref=(string)$cell['r'];
                if(!preg_match('/^([A-Z]{1,2})[1-9][0-9]{0,6}$/D',$ref,$m)) {
                    throw new RuntimeException('Invalid spreadsheet cell reference.');
                }
                $col=0;
                foreach(str_split($m[1]) as $letter) {
                    $col=$col*26+ord($letter)-64;
                }
                $col--;
                if($col>=6) {
                    if((string)$cell!=='' || $cell->xpath('./*[local-name()="v" or local-name()="is" or local-name()="f"]')) {
                        throw new RuntimeException('Spreadsheet must contain exactly the template columns.');
                    }
                    continue;
                }
                if(isset($seenColumns[$col])){throw new RuntimeException('Duplicate spreadsheet cell reference.');}
                $seenColumns[$col]=true;
                if($cell->xpath('./*[local-name()="f"]')) {
                    $formula=true;
                }
                $values=$cell->xpath('./*[local-name()="v"]');
                $value=isset($values[0])?(string)$values[0]:'';
                if((string)$cell['t']==='s') {
                    if(!ctype_digit($value) || !isset($strings[(int)$value])) {
                        throw new RuntimeException('Invalid shared-string reference.');
                    }
                    $value=$strings[(int)$value];
                }
                if((string)$cell['t']==='inlineStr') {
                    $value='';
                    foreach($cell->xpath('.//*[local-name()="t"]') as $t) {
                        $value.=(string)$t;
                    }
                }
                if(strlen($value)>1024) {
                    throw new RuntimeException('Spreadsheet cell exceeds the limit.');
                }
                $row[$col]=$value;
            }
            if($formula) {
                $row['_formula']=true;
            }
            $rows[]=$row;
        }
        unset($archive);
        return $rows;
    } catch(UnexpectedValueException $e) {
        throw new RuntimeException('Invalid XLSX archive.');
    } finally {
        unset($archive);
        private_delete('imports',$zipName);
    }
}
function parse_import_file(array $file): array {
    try {$meta=inspect_upload($file,IMPORT_MAX_BYTES,['csv'=>['text/plain','text/csv','application/csv','application/octet-stream'],'xlsx'=>['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream']]);}
    catch(RuntimeException $e){throw new ValidationException(['file'=>user_safe_error($e)]);}
    if($meta['extension']==='xlsx') {
        $rows=xlsx_rows($file['tmp_name']);
    } else {
        $h=fopen($file['tmp_name'],'rb');
        $rows=[];
        try {
            while(($row=fgetcsv($h,8192,',','"',''))!==false) {
                if(count($rows)>IMPORT_MAX_ROWS) {
                    throw new RuntimeException('Import at most '.IMPORT_MAX_ROWS.' rows.');
                }
                if($row===[null]) {
                    continue;
                }
                $rows[]=$row;
            }
        } finally {
            fclose($h);
        }
    }
    if(!$rows) {
        throw new RuntimeException('Import file is empty.');
    }
    $header=array_shift($rows);
    $header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)($header[0]??''));
    if($header!==STUDENT_IMPORT_HEADERS) {
        throw new RuntimeException('Headers must match the template exactly: '.implode(', ',STUDENT_IMPORT_HEADERS));
    }
    return $rows;
}
function preview_import(PDO $pdo,array $rows): array {
    if(count($rows)>IMPORT_MAX_ROWS) {
        throw new RuntimeException('Too many import rows.');
    }
    $preview=[];
    $occurrences=[];
    $emailOccurrences=[];
    foreach($rows as $row) {
        $reg=mb_strtolower(trim((string)($row[1]??'')));
        $occurrences[$reg]=($occurrences[$reg]??0)+1;
        $email=is_string($row[5]??null)?mb_strtolower(trim($row[5])):'';
        if($email!==''){$emailOccurrences[$email]=($emailOccurrences[$email]??0)+1;}
    }
    foreach($rows as $index=>$cells) {
        $data=[];
        foreach(STUDENT_IMPORT_HEADERS as $column=>$name) {
            $data[$name]=is_string($cells[$column]??null)?$cells[$column]:'';
        }
        $errors=[];
        $normalized=null;
        try {
            if(isset($cells['_formula'])) {
                throw new RuntimeException('Spreadsheet formulas are not accepted.');
            }
            if(count(array_filter(array_keys($cells),'is_int'))!==6) {
                throw new RuntimeException('Expected six columns.');
            }
            if(($occurrences[mb_strtolower(trim($data['registration_number']))]??0)>1) {
                throw new RuntimeException('Duplicate registration number within this file.');
            }
            $email=mb_strtolower(trim($data['email']));
            if($email!==''&&($emailOccurrences[$email]??0)>1){throw new ValidationException(['email'=>'Duplicate email address within this file.']);}
            $normalized=validate_student_row($pdo,$data);
        } catch(Throwable $e) {
            $errors=[$e instanceof PDOException?'Validation could not be completed.':$e->getMessage()];
        }
        $preview[]=['line'=>$index+2,'data'=>$data,'normalized'=>$normalized,'errors'=>$errors];
    }
    return $preview;
}
function commit_import(PDO $pdo,array $preview,int $adminId): array {
    $result=['created'=>0,'skipped'=>0,'failed'=>0,'messages'=>[]];
    foreach($preview as $item) {
        if($item['errors']) {
            $result['skipped']++;
            continue;
        }
        $pdo->beginTransaction();
        try {
            lock_account_creation($pdo);
            $row=validate_student_row($pdo,$item['data']);
            $id=create_student_account($pdo,$row);
            audit($pdo,'STUDENT_IMPORTED',$adminId,null,'New account #'.$id.' from import row '.$item['line']);
            $pdo->commit();
            $result['created']++;
        }
        catch(Throwable $e) {
            if($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if($e instanceof ValidationException || ($e instanceof PDOException && ($e->errorInfo[1]??0)===1062)) {
                $result['skipped']++;
                $result['messages'][]='Row '.$item['line'].': already exists or changed validation; skipped.';
            } else {
                $result['failed']++;
                $result['messages'][]='Row '.$item['line'].': could not be saved.';
            }
        }
    }
    $pdo->prepare('INSERT INTO import_runs(admin_id,created_count,skipped_count,failed_count) VALUES (?,?,?,?)')->execute([$adminId,$result['created'],$result['skipped'],$result['failed']]);
    audit($pdo,'STUDENT_IMPORT_COMPLETED',$adminId,null,'Created '.$result['created'].'; skipped '.$result['skipped'].'; failed '.$result['failed']);
    return $result;
}
function student_template_rows(): array {
    return [STUDENT_IMPORT_HEADERS,['Alex Example','IRDP/ODICT/MA26/9001','ODICT','EPM','2026/2027','alex@example.invalid'],['Sam Example','IRDP/BTCRP/MA26/9002','BTCRP','EPM','2026/2027','sam@example.invalid']];
}
