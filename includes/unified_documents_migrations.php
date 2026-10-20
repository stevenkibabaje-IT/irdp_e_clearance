<?php
declare(strict_types=1);

/** Merge legacy certificate identity into its document, preserving snapshot bytes and IDs. */
function migrate_unified_documents(PDO $db): void {
    if ($db->query("SELECT version FROM schema_migrations WHERE version='2026_unified_documents_v1'")->fetchColumn()) { return; }
    if ($db->inTransaction()) { throw new LogicException('Document schema migration requires no open transaction.'); }
    foreach (['version'=>'INT NULL','document_number'=>'VARCHAR(80) NULL','verification_token'=>'CHAR(64) NULL',
        'snapshot_json'=>'LONGTEXT NULL','snapshot_sha256'=>'CHAR(64) NULL','generated_by'=>'INT NULL'] as $column=>$definition) {
        $db->exec('ALTER TABLE transcripts MODIFY `'.$column.'` '.$definition);
    }
    foreach (['certificate_number'=>'VARCHAR(80) NULL','verification_code'=>'VARCHAR(80) NULL',
        'issued_at'=>'DATETIME NULL','legacy_certificate_id'=>'INT NULL',
        'certificate_request_id'=>'INT GENERATED ALWAYS AS (CASE WHEN certificate_number IS NOT NULL THEN clearance_request_id ELSE NULL END) STORED'] as $column=>$definition) {
        ensure_column($db,'transcripts',$column,$definition);
    }
    foreach (['certificate_number','verification_code','legacy_certificate_id','certificate_request_id'] as $column) {
        $s=$db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transcripts' AND INDEX_NAME=?");
        $s->execute(['unified_'.$column]);
        if (!(int)$s->fetchColumn()) { $db->exec('CREATE UNIQUE INDEX unified_'.$column.' ON transcripts (`'.$column.'`)'); }
    }
    $exists=(bool)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
    if ($exists) {
        $incoming=(int)$db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='certificates'")->fetchColumn();
        if ($incoming) { throw new RuntimeException('A foreign key still references certificates. Preserve that relationship before merging.'); }
        $db->beginTransaction();
        try {
            foreach ($db->query('SELECT * FROM certificates ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $certificate) {
                $s=$db->prepare('SELECT id FROM transcripts WHERE certificate_number=?'); $s->execute([$certificate['certificate_number']]);
                $id=$s->fetchColumn();
                if (!$id) {
                    $s=$db->prepare('SELECT id FROM transcripts WHERE clearance_request_id=? ORDER BY id LIMIT 1 FOR UPDATE');
                    $s->execute([$certificate['clearance_request_id']]); $id=$s->fetchColumn();
                    if (!$id) {
                        // Identity-only historical certificates have no fabricated snapshot or issuer.
                        $s=$db->prepare('INSERT INTO transcripts(student_id,clearance_request_id,cycle_id,result_source,generated_at)
                            SELECT r.student_id,r.id,a.id,"CLEARANCE",? FROM clearance_requests r
                            JOIN academic_cycles a ON a.label=r.academic_year WHERE r.id=?');
                        $s->execute([$certificate['issued_at'],$certificate['clearance_request_id']]);
                        if ($s->rowCount()!==1) { throw new RuntimeException('A legacy certificate has no matching request and academic cycle.'); }
                        $id=(int)$db->lastInsertId();
                    }
                    $s=$db->prepare('UPDATE transcripts SET certificate_number=?,verification_code=?,issued_at=?,legacy_certificate_id=? WHERE id=?');
                    $s->execute([$certificate['certificate_number'],$certificate['verification_code'],$certificate['issued_at'],$certificate['id'],$id]);
                }
            }
            $mismatches=(int)$db->query('SELECT COUNT(*) FROM certificates c LEFT JOIN transcripts t ON t.legacy_certificate_id=c.id
                WHERE t.id IS NULL OR NOT(t.certificate_number <=> c.certificate_number) OR NOT(t.verification_code <=> c.verification_code)
                OR NOT(t.issued_at <=> c.issued_at) OR t.clearance_request_id<>c.clearance_request_id')->fetchColumn();
            if ($mismatches) { throw new RuntimeException('Certificate copy verification failed; the original table has been retained.'); }
            $db->commit();
        } catch (Throwable $e) { if($db->inTransaction()){$db->rollBack();} throw $e; }
        // DDL commits implicitly. The checked copy is durable before removing the source.
        $db->exec('DROP TABLE certificates');
    }
    $db->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_unified_documents_v1')");
}
