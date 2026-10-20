<?php
declare(strict_types=1);
/** Read-only schema and aggregate integrity audit. No account details are printed. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../config/database.php';
$db=db_connect();
$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$keys=$db->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
$inventory=[]; $orphans=0;
foreach ($tables as $table) {
    $pk=$db->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");
    $pk->execute([$table]);
    $inventory[$table]=['rows'=>(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn(),'primary_key'=>$pk->fetchAll(PDO::FETCH_COLUMN)];
}
foreach ($keys as $key) {
    $count=(int)$db->query('SELECT COUNT(*) FROM `'.$key['TABLE_NAME'].'` c LEFT JOIN `'.$key['REFERENCED_TABLE_NAME'].'` p ON c.`'.$key['COLUMN_NAME'].'`=p.`'.$key['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$key['COLUMN_NAME'].'` IS NOT NULL AND p.`'.$key['REFERENCED_COLUMN_NAME'].'` IS NULL')->fetchColumn();
    $orphans+=$count;
}
$queries=[
    'unmapped_programmes'=>"SELECT COUNT(*) FROM students s LEFT JOIN programmes p ON p.code=s.programme WHERE s.programme IS NOT NULL AND p.id IS NULL",
    'unmapped_request_years'=>"SELECT COUNT(*) FROM clearance_requests r LEFT JOIN academic_cycles a ON a.label=r.academic_year WHERE a.id IS NULL",
    'different_student_user_emails'=>"SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE NOT (NULLIF(TRIM(s.email),'') <=> NULLIF(TRIM(u.email),''))",
    'different_student_user_phones'=>"SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE NOT (NULLIF(TRIM(s.phone),'') <=> NULLIF(TRIM(u.phone),''))",
    'different_student_user_departments'=>"SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE NOT (s.department_id <=> u.department_id)",
    'duplicate_request_step_groups'=>"SELECT COUNT(*) FROM (SELECT clearance_request_id,workflow_step_id FROM clearance_stages GROUP BY clearance_request_id,workflow_step_id HAVING COUNT(*)>1) t",
    'stage_workflow_office_mismatches'=>"SELECT COUNT(*) FROM clearance_stages s JOIN workflow_steps w ON w.id=s.workflow_step_id WHERE s.office_id<>w.office_id",
    'invalid_original_officers'=>"SELECT COUNT(*) FROM clearance_stages s LEFT JOIN users u ON u.id=s.original_officer_id WHERE s.original_officer_id IS NOT NULL AND u.id IS NULL",
    'invalid_password_issuers'=>"SELECT COUNT(*) FROM password_resets p LEFT JOIN users u ON u.id=p.issued_by WHERE p.issued_by IS NOT NULL AND u.id IS NULL",
    'invalid_or_wrong_payment_receipts'=>"SELECT COUNT(*) FROM clearance_fee_payments p LEFT JOIN clearance_fee_receipts r ON r.id=p.receipt_id WHERE p.receipt_id IS NOT NULL AND (r.id IS NULL OR r.payment_id<>p.id)",
    'actions_without_review_cycle'=>"SELECT COUNT(*) FROM stage_actions a LEFT JOIN review_cycles r ON r.stage_id=a.stage_id AND r.cycle_number=a.review_cycle WHERE r.id IS NULL",
    'evidence_without_review_cycle'=>"SELECT COUNT(*) FROM stage_evidence e LEFT JOIN review_cycles r ON r.stage_id=e.stage_id AND r.cycle_number=e.cycle_number WHERE r.id IS NULL",
    'transcript_request_mismatches'=>"SELECT COUNT(*) FROM transcripts t JOIN clearance_requests r ON r.id=t.clearance_request_id JOIN academic_cycles a ON a.id=t.cycle_id WHERE t.student_id<>r.student_id OR a.label<>r.academic_year",
];
$checks=[];
foreach ($queries as $name=>$sql) { $checks[$name]=(int)$db->query($sql)->fetchColumn(); }
echo json_encode(['table_count'=>count($tables),'primary_key_tables'=>count(array_filter($inventory,static fn($t)=>count($t['primary_key'])>0)),
    'foreign_key_columns'=>count($keys),'declared_fk_orphans'=>$orphans,'checks'=>$checks,'tables'=>$inventory,'relationships'=>$keys],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
