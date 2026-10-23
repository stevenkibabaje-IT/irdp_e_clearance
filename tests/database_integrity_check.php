<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$db = db_connect();
$relationships = $db->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll();
$constraints=[];
foreach ($relationships as $relationship) {
    $constraints[$relationship['TABLE_NAME'].'.'.$relationship['CONSTRAINT_NAME']][]=$relationship;
}
$orphaned=[];
foreach ($constraints as $name=>$columns) {
    $first=$columns[0];$on=[];$notNull=[];
    foreach ($columns as $column) {
        $on[]='c.`'.$column['COLUMN_NAME'].'`=p.`'.$column['REFERENCED_COLUMN_NAME'].'`';
        $notNull[]='c.`'.$column['COLUMN_NAME'].'` IS NOT NULL';
    }
    $count=(int)$db->query('SELECT COUNT(*) FROM `'.$first['TABLE_NAME'].'` c LEFT JOIN `'.$first['REFERENCED_TABLE_NAME'].'` p ON '.implode(' AND ',$on).' WHERE '.implode(' AND ',$notNull).' AND p.`'.$first['REFERENCED_COLUMN_NAME'].'` IS NULL')->fetchColumn();
    if ($count) { $orphaned[$name]=$count; }
}
$counts=[];
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $counts[$table]=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
}
echo json_encode(['database'=>DB_NAME,'tables'=>count($counts),'foreign_keys_checked'=>count($constraints),'orphaned_relationships'=>$orphaned,'row_counts'=>$counts],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit($orphaned?1:0);
