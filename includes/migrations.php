<?php
declare(strict_types=1);

/** Schema and data upgrades for application features. */
const RETIRED_FEATURE_TABLES = [
    'officer_availability', 'officer_backup', 'clearance_escalations',
    'emergency_clearances', 'office_supervisors', 'assignment_history',
    'stage_events', 'stage_appeals', 'app_settings',
];

/** Remove only unused legacy tables, after checking every candidate before DDL. */
function migrate_retired_feature_tables(PDO $db): void {
    $existing = array_intersect(RETIRED_FEATURE_TABLES, $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    foreach ($existing as $table) {
        $where = $table === 'app_settings'
            ? " WHERE name NOT IN ('reminder_hours','escalation_hours','resubmission_limit')"
            : '';
        if ((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`'.$where)->fetchColumn()) {
            throw new RuntimeException('Archive legacy records before removing '.$table.'. No retired tables were removed.');
        }
        $references = $db->prepare('SELECT TABLE_SCHEMA,TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=?');
        $references->execute([$table]);
        $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        foreach ($references->fetchAll(PDO::FETCH_ASSOC) as $reference) {
            if ($reference['TABLE_SCHEMA'] !== $database || !in_array($reference['TABLE_NAME'], RETIRED_FEATURE_TABLES, true)) {
                throw new RuntimeException('A retained table references '.$table.'. No retired tables were removed.');
            }
        }
    }
    if ($existing) {
        $db->exec('DROP TABLE '.implode(', ', array_map(fn($table) => '`'.$table.'`', $existing)));
    }
    $db->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_retired_feature_tables_v1')");
}

function migrate_retired_notification_settings(PDO $db): void {
    // Remove only the retired integration's queue and preference, keeping phone data.
    $db->exec('DROP TABLE IF EXISTS sms_outbox');
    $column=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='sms_opt_in'");
    if((int)$column->fetchColumn()) {$db->exec('ALTER TABLE students DROP COLUMN sms_opt_in');}
    $db->exec("DELETE FROM schema_migrations WHERE version IN ('2026_clearance_sms_v1','2026_runtime_v3_sms')");
}

function migrate_password_recovery_requests(PDO $db): void {
    if ($db->query("SELECT version FROM schema_migrations WHERE version='2026_recovery_requests_v1'")->fetchColumn()) {
        return;
    }
    $db->exec('CREATE TABLE IF NOT EXISTS password_reset_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        status ENUM("PENDING","ISSUED","COMPLETED","REJECTED") NOT NULL DEFAULT "PENDING",
        reset_id INT NULL,
        handled_by INT NULL,
        identity_reference VARCHAR(255) NULL,
        decision_reason VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        handled_at DATETIME NULL,
        completed_at DATETIME NULL,
        INDEX idx_recovery_status (status,id),
        INDEX idx_recovery_user (user_id,status),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(reset_id) REFERENCES password_resets(id),
        FOREIGN KEY(handled_by) REFERENCES users(id)
    ) ENGINE=InnoDB');
    $db->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_recovery_requests_v1')");
}

function migrate_features(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    if ($db->query("SELECT version FROM schema_migrations WHERE version = '2026_features_v1'")->fetchColumn()) {
        return;
    }
    $columns = [
    'users' => ['auth_version' => 'INT NOT NULL DEFAULT 1', 'force_password_change' => 'TINYINT NOT NULL DEFAULT 0', 'email_verified_at' => 'DATETIME NULL'],
    'students' => ['profile_file' => 'VARCHAR(80) NULL'],
    'clearance_stages' => ['assignment_status' => "VARCHAR(20) NOT NULL DEFAULT 'ASSIGNED'", 'assignment_source' => "VARCHAR(20) NOT NULL DEFAULT 'ORIGINAL'", 'original_officer_id' => 'INT NULL', 'review_cycle' => 'INT NOT NULL DEFAULT 1', 'actionable_at' => 'DATETIME NULL', 'resubmissions' => 'INT NOT NULL DEFAULT 0', 'corrective_instructions' => 'TEXT NULL', 'urgent' => 'TINYINT NOT NULL DEFAULT 0', 'urgent_reason' => 'TEXT NULL'],
    'stage_actions' => ['review_cycle' => 'INT NOT NULL DEFAULT 1', 'corrective_instructions' => 'TEXT NULL'],
    ];
    foreach ($columns as $table => $items) {
        foreach ($items as $column => $definition) {
            ensure_column($db, $table, $column, $definition);
        }
    }
    $tables = [
    'programmes' => 'id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, active TINYINT NOT NULL DEFAULT 1',
    'review_cycles' => 'id INT AUTO_INCREMENT PRIMARY KEY, stage_id INT NOT NULL, cycle_number INT NOT NULL, opened_at DATETIME NULL, closed_at DATETIME NULL, student_response TEXT NULL, UNIQUE KEY cycle_unique (stage_id, cycle_number), FOREIGN KEY (stage_id) REFERENCES clearance_stages(id)',
    'stage_evidence' => 'id INT AUTO_INCREMENT PRIMARY KEY, stage_id INT NOT NULL, cycle_number INT NOT NULL, uploader_id INT NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE, original_name VARCHAR(180) NOT NULL, mime_type VARCHAR(80) NOT NULL, byte_size INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(stage_id) REFERENCES clearance_stages(id), FOREIGN KEY(uploader_id) REFERENCES users(id)',
    'password_resets' => 'id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, issued_by INT NULL, identity_reference VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id)',
    'rate_limits' => 'bucket_hash CHAR(64) PRIMARY KEY, window_start DATETIME NOT NULL, attempts INT NOT NULL DEFAULT 0',
    'import_runs' => 'id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, created_count INT NOT NULL, skipped_count INT NOT NULL, failed_count INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(admin_id) REFERENCES users(id)',
    ];
    foreach ($tables as $name => $definition) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $name . ' (' . $definition . ') ENGINE=InnoDB');
    }
    $db->exec("INSERT IGNORE INTO roles(name) VALUES ('SUPERVISOR')");
    $db->exec("INSERT IGNORE INTO programmes(code,name) VALUES ('ODICT','ODICT'),('BTCRP','BTCRP'),('BTCCD','BTCCD')");
    $db->exec('INSERT IGNORE INTO programmes(code,name) SELECT DISTINCT programme, programme FROM students WHERE programme IS NOT NULL AND programme <> "" AND CHAR_LENGTH(programme) <= 40');
    $db->exec('UPDATE clearance_stages SET original_officer_id = assigned_officer_id, actionable_at = CASE WHEN status IN ("PENDING","IN_REVIEW","ESCALATED") THEN COALESCE(started_at, NOW()) ELSE NULL END');
    // Preserve rejection/approval history when converting the old combined escalation state.
    $db->exec('UPDATE clearance_stages cs SET assignment_status = CASE WHEN cs.status = "ESCALATED" THEN "ESCALATED" WHEN cs.assigned_officer_id IS NULL THEN "UNASSIGNED" ELSE "ASSIGNED" END, status = CASE WHEN cs.status = "ESCALATED" THEN CASE WHEN (SELECT sa.action FROM stage_actions sa WHERE sa.stage_id = cs.id ORDER BY sa.id DESC LIMIT 1) = "REJECTED" THEN "REJECTED" ELSE "PENDING" END ELSE cs.status END');
    $db->exec('UPDATE clearance_requests cr SET status = CASE WHEN EXISTS(SELECT 1 FROM clearance_stages cs WHERE cs.clearance_request_id = cr.id AND cs.status = "REJECTED") THEN "PAUSED" ELSE "IN_PROGRESS" END WHERE cr.status = "ESCALATED"');
    $db->exec('INSERT IGNORE INTO review_cycles(stage_id,cycle_number,opened_at,closed_at) SELECT id,review_cycle,actionable_at,CASE WHEN status IN ("APPROVED","REJECTED") THEN reviewed_at ELSE NULL END FROM clearance_stages');
    $db->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_features_v1')");
}
