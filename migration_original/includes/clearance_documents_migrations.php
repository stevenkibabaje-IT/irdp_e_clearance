<?php
declare(strict_types=1);

/** Install clearance periods, academic cycles and transcript storage. */

function migrate_clearance_documents(PDO $db): void {
    $alreadyInstalled=(bool)$db->query("SELECT version FROM schema_migrations WHERE version='2026_clearance_documents_v3'")->fetchColumn();
    $tables=[
        'academic_cycles'=>'id INT AUTO_INCREMENT PRIMARY KEY,label VARCHAR(20) NOT NULL UNIQUE,active TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'clearance_period'=>'id TINYINT PRIMARY KEY,cycle_id INT NULL,mode ENUM("MANUAL_OPEN","MANUAL_CLOSED","SCHEDULED") NOT NULL DEFAULT "MANUAL_CLOSED",opens_at DATETIME NULL,closes_at DATETIME NULL,updated_by INT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,remarks VARCHAR(1000) NOT NULL DEFAULT "",FOREIGN KEY(cycle_id) REFERENCES academic_cycles(id),FOREIGN KEY(updated_by) REFERENCES users(id)',
        'transcripts'=>'id INT AUTO_INCREMENT PRIMARY KEY,student_id INT NOT NULL,clearance_request_id INT NOT NULL,cycle_id INT NOT NULL,version INT NOT NULL,document_number VARCHAR(80) NOT NULL UNIQUE,verification_token CHAR(64) NOT NULL UNIQUE,snapshot_json LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,result_source ENUM("DEMO","OFFICIAL","CLEARANCE") NOT NULL,status ENUM("VALID","REVOKED") NOT NULL DEFAULT "VALID",generated_by INT NOT NULL,generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,revoked_by INT NULL,revoked_at DATETIME NULL,revocation_reason VARCHAR(1000) NULL,UNIQUE KEY transcript_version(student_id,version),INDEX transcript_request(clearance_request_id,status),FOREIGN KEY(student_id) REFERENCES students(id),FOREIGN KEY(clearance_request_id) REFERENCES clearance_requests(id),FOREIGN KEY(cycle_id) REFERENCES academic_cycles(id),FOREIGN KEY(generated_by) REFERENCES users(id),FOREIGN KEY(revoked_by) REFERENCES users(id)',
    ];
    foreach($tables as $table=>$definition){$db->exec('CREATE TABLE IF NOT EXISTS '.$table.' ('.$definition.') ENGINE=InnoDB');}
    $sourceType=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transcripts' AND COLUMN_NAME='result_source'")->fetchColumn();
    if(!str_contains((string)$sourceType,"'CLEARANCE'")){
        $db->exec('ALTER TABLE transcripts MODIFY result_source ENUM("DEMO","OFFICIAL","CLEARANCE") NOT NULL');
    }
    $labels=$db->query('SELECT DISTINCT academic_year FROM clearance_requests UNION SELECT DISTINCT year_of_study FROM students')->fetchAll(PDO::FETCH_COLUMN);
    $labels[]=date('Y').'/'.((int)date('Y')+1);
    $insert=$db->prepare('INSERT IGNORE INTO academic_cycles(label) VALUES (?)');
    foreach($labels as $label){if(is_string($label)&&preg_match('/^(20[0-9]{2})\/(20[0-9]{2})$/D',$label,$m)&&(int)$m[2]===(int)$m[1]+1){$insert->execute([$label]);}}
    $db->exec("INSERT IGNORE INTO programmes(code,name) VALUES ('ODDAM','Ordinary Diploma in Development Administration and Management'),('ODCD','Ordinary Diploma in Community Development'),('ODDP','Ordinary Diploma in Development Planning'),('BDURP','Bachelor Degree in Urban and Regional Planning'),('BTCICT','Basic Technician Certificate in Information Communication Technology'),('BTCURP','Basic Technician Certificate in Urban and Regional Planning')");
    $db->exec('INSERT IGNORE INTO clearance_period(id,remarks) VALUES (1,"Awaiting an administrator to open clearance.")');
    if(!$alreadyInstalled){$db->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_clearance_documents_v3')");}
}
