<?php
declare(strict_types=1);

/** Database setup and reference/demo data seeding; normal requests check schema readiness first. */

require_once __DIR__ . '/../config/database.php';

// Bump when schema or initial reference-data requirements change.
const APPLICATION_SCHEMA_VERSION = '2026_runtime_v13_email_notifications';

function database_schema_ready(PDO $db): bool
{
    try {
        $stmt=$db->prepare('SELECT version FROM schema_migrations WHERE version=?');
        $stmt->execute([APPLICATION_SCHEMA_VERSION]);
        return (bool)$stmt->fetchColumn();
    } catch(PDOException $e) {
        if((int)($e->errorInfo[1]??0)===1146){return false;}
        throw $e;
    }
}

function application_database(): PDO
{
    try {$db=db_connect();}
    catch(PDOException $e) {
        if((int)($e->errorInfo[1]??0)!==1049){throw $e;}
        return ensure_database_ready(null,false);
    }
    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return database_schema_ready($db)?$db:ensure_database_ready($db,false);
}

/**
 * Prepare the local IRDP database automatically.
 *
 * This is intentionally idempotent: it creates missing tables/columns and
 * seeds only missing reference data and demo accounts. Retired notification
 * settings are removed; student and clearance records are preserved.
 */
function ensure_database_ready(?PDO $db = null, bool $force = true): PDO
{
    if ($db === null) {
        $server = db_connect(false);
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` '
            . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $server = null;

        $db = db_connect(true);
    }
    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $schemaLock = $db->prepare('SELECT GET_LOCK(CONCAT(DATABASE(), ":irdp_schema"), 30)');
    $schemaLock->execute();
    if ((int)$schemaLock->fetchColumn() !== 1) { throw new RuntimeException('Database migration is busy. Retry shortly.'); }
    try {
    // Another first request may have completed setup while this one waited.
    if(!$force && database_schema_ready($db)){return $db;}
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tables = [
        'roles' => <<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB
SQL,
        'departments' => <<<'SQL'
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB
SQL,
        'users' => <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(160) NULL,
    phone VARCHAR(40) NULL,
    role_id INT NOT NULL,
    department_id INT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
        'students' => <<<'SQL'
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    registration_number VARCHAR(80) NOT NULL UNIQUE,
    programme VARCHAR(120) NULL,
    year_of_study VARCHAR(40) NULL,
    dob DATE NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(160) NULL,
    department_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
        'offices' => <<<'SQL'
CREATE TABLE IF NOT EXISTS offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB
SQL,
        'user_offices' => <<<'SQL'
CREATE TABLE IF NOT EXISTS user_offices (
    user_id INT NOT NULL,
    office_id INT NOT NULL,
    PRIMARY KEY (user_id, office_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL,
        'workflow_steps' => <<<'SQL'
CREATE TABLE IF NOT EXISTS workflow_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    step_number INT NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    office_id INT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB
SQL,
        'clearance_requests' => <<<'SQL'
CREATE TABLE IF NOT EXISTS clearance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL DEFAULT '2025/2026',
    status ENUM('NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'NOT_STARTED',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_clearance_cycle (student_id, academic_year)
) ENGINE=InnoDB
SQL,
        'clearance_stages' => <<<'SQL'
CREATE TABLE IF NOT EXISTS clearance_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clearance_request_id INT NOT NULL,
    workflow_step_id INT NOT NULL,
    office_id INT NOT NULL,
    assigned_officer_id INT NULL,
    status ENUM('LOCKED','PENDING','IN_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    started_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    comments TEXT NULL,
    details_json LONGTEXT NULL,
    FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_step_id) REFERENCES workflow_steps(id),
    FOREIGN KEY (office_id) REFERENCES offices(id),
    FOREIGN KEY (assigned_officer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
        'stage_actions' => <<<'SQL'
CREATE TABLE IF NOT EXISTS stage_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_id INT NOT NULL,
    officer_id INT NOT NULL,
    action ENUM('APPROVED','REJECTED','PAYMENT_REQUESTED') NOT NULL,
    comments TEXT NULL,
    details_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES clearance_stages(id) ON DELETE CASCADE,
    FOREIGN KEY (officer_id) REFERENCES users(id)
) ENGINE=InnoDB
SQL,
        'notifications' => <<<'SQL'
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL,
        'certificates' => <<<'SQL'
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clearance_request_id INT NOT NULL UNIQUE,
    certificate_number VARCHAR(80) NOT NULL UNIQUE,
    verification_code VARCHAR(80) NOT NULL UNIQUE,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL,
        'audit_logs' => <<<'SQL'
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    clearance_request_id INT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB
SQL,
        'officer_availability' => <<<'SQL'
CREATE TABLE IF NOT EXISTS officer_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    status ENUM('AVAILABLE','UNAVAILABLE','ON_LEAVE','SUSPENDED','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
    start_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_at DATETIME NULL,
    reason VARCHAR(255) NULL,
    changed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_officer_availability_officer (officer_id),
    INDEX idx_officer_availability_status (status)
) ENGINE=InnoDB
SQL,
        'officer_backup' => <<<'SQL'
CREATE TABLE IF NOT EXISTS officer_backup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    primary_officer_id INT NOT NULL,
    backup_officer_id INT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    reason VARCHAR(255) NULL,
    authorized_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    FOREIGN KEY (primary_officer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (backup_officer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_backup_map (office_id, primary_officer_id, backup_officer_id),
    INDEX idx_officer_backup_active (active)
) ENGINE=InnoDB
SQL,
        'clearance_escalations' => <<<'SQL'
CREATE TABLE IF NOT EXISTS clearance_escalations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clearance_request_id INT NOT NULL,
    stage_id INT NULL,
    office_id INT NULL,
    primary_officer_id INT NULL,
    current_officer_id INT NULL,
    escalation_level INT NOT NULL DEFAULT 1,
    reason VARCHAR(255) NOT NULL,
    status ENUM('OPEN','PENDING','RESOLVED') NOT NULL DEFAULT 'OPEN',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES clearance_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE SET NULL,
    FOREIGN KEY (primary_officer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (current_officer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_clearance_escalations_request (clearance_request_id),
    INDEX idx_clearance_escalations_status (status)
) ENGINE=InnoDB
SQL,
        'emergency_clearances' => <<<'SQL'
CREATE TABLE IF NOT EXISTS emergency_clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clearance_request_id INT NOT NULL,
    requester_user_id INT NOT NULL,
    reason_category VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    requested_priority VARCHAR(40) NOT NULL DEFAULT 'HIGH',
    status ENUM('NORMAL','PRIORITY_REQUESTED','UNDER_REVIEW','PRIORITY_APPROVED','PRIORITY_REJECTED','ESCALATED','RESOLVED') NOT NULL DEFAULT 'NORMAL',
    reviewer_user_id INT NULL,
    decision_reason TEXT NULL,
    decision_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_active_emergency (clearance_request_id, status),
    INDEX idx_emergency_clearances_status (status)
) ENGINE=InnoDB
SQL,
    ];

    foreach ($tables as $sql) {
        $db->exec($sql);
    }

    // Lightweight migrations for databases created by earlier versions.
    ensure_column($db, 'users', 'department_id', 'INT NULL AFTER role_id');
    ensure_column($db, 'students', 'department_id', 'INT NULL AFTER email');
    ensure_column($db, 'clearance_stages', 'details_json', 'LONGTEXT NULL AFTER comments');
    ensure_column($db, 'stage_actions', 'details_json', 'LONGTEXT NULL AFTER comments');

    $requestStatusType = $db->query("SHOW COLUMNS FROM clearance_requests LIKE 'status'")->fetch();
    if (!str_contains((string) $requestStatusType['Type'], "'ESCALATED'") || !str_contains((string) $requestStatusType['Type'], "'ON_HOLD'")) {
        $db->exec(
            "ALTER TABLE clearance_requests MODIFY COLUMN status ENUM('NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED','ESCALATED','ON_HOLD') NOT NULL DEFAULT 'NOT_STARTED'"
        );
    }

    $stageStatusType = $db->query("SHOW COLUMNS FROM clearance_stages LIKE 'status'")->fetch();
    if (!str_contains((string) $stageStatusType['Type'], "'ESCALATED'")) {
        $db->exec(
            "ALTER TABLE clearance_stages MODIFY COLUMN status ENUM('LOCKED','PENDING','IN_REVIEW','APPROVED','REJECTED','ESCALATED') NOT NULL DEFAULT 'PENDING'"
        );
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    require_once __DIR__ . '/migrations.php';
    migrate_features($db);
    migrate_password_recovery_requests($db);
    migrate_retired_notification_settings($db);

    require_once __DIR__ . '/email_notification_migrations.php';
    migrate_email_notifications($db);

    seed_reference_data($db);
    seed_demo_accounts($db);

    require_once __DIR__.'/clearance_documents_migrations.php';
    migrate_clearance_documents($db);

    require_once __DIR__.'/parallel_clearance_migrations.php';
    migrate_parallel_clearance($db);

    require_once __DIR__.'/finance_payment_migrations.php';
    migrate_finance_payment_requests($db);

    require_once __DIR__.'/clearance_fee_migrations.php';
    migrate_clearance_fees($db);

    foreach([
        ['notifications','idx_notifications_unread','user_id,is_read'],
        ['clearance_requests','idx_request_progress','status,id'],
        ['clearance_requests','idx_student_latest','student_id,id'],
        ['clearance_stages','idx_officer_queue','assigned_officer_id,status,clearance_request_id'],
    ] as [$table,$name,$columns]) {
        $index=$db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $index->execute([$table,$name]);
        if(!(int)$index->fetchColumn()){$db->exec('CREATE INDEX '.$name.' ON '.$table.' ('.$columns.')');}
    }
    $duplicateCycles=(int)$db->query('SELECT COUNT(*) FROM (SELECT student_id,academic_year FROM clearance_requests GROUP BY student_id,academic_year HAVING COUNT(*)>1) duplicate_cycles')->fetchColumn();
    if($duplicateCycles){throw new RuntimeException('Duplicate student clearance cycles must be reconciled before applying the unique cycle constraint. No records were changed.');}
    $cycleIndex=$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clearance_requests' AND INDEX_NAME='unique_student_clearance_cycle'");
    if(!(int)$cycleIndex->fetchColumn()){$db->exec('CREATE UNIQUE INDEX unique_student_clearance_cycle ON clearance_requests (student_id,academic_year)');}
    $version=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES (?)');
    $version->execute([APPLICATION_SCHEMA_VERSION]);

    return $db;
    } finally {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $db->query('SELECT RELEASE_LOCK(CONCAT(DATABASE(), ":irdp_schema"))');
    }
}

function ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

function seed_reference_data(PDO $db): void
{
    foreach (['STUDENT', 'OFFICER', 'ADMIN'] as $role) {
        $stmt = $db->prepare('INSERT IGNORE INTO roles (name) VALUES (?)');
        $stmt->execute([$role]);
    }

    $department = $db->prepare(
        'INSERT INTO departments (code, name, active)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE code = code'
    );
    $department->execute([
        'EPM',
        'Department of Environmental Planning and Management',
    ]);

    $offices = [
        ['Librarian', 'Library clearance'],
        ['Sports and Games (Matron)', 'Sports and games / matron clearance'],
        ['Computer Lab/Room in Charge', 'Computer lab or room clearance'],
        ['Supplies Officer', 'Supplies clearance'],
        ['Transport Officer', 'Transport clearance'],
        ['Dispensary', 'Dispensary clearance'],
        ['Head of Department', 'Departmental clearance'],
        ['Hostels Superintendent (Accommodation Officer)', 'Accommodation and hostel clearance'],
        ['Director of Student Affairs', 'Student affairs clearance'],
        ['Admissions Officer', 'Admissions clearance'],
        ['Director of Finance and Accounts', 'Final finance clearance'],
    ];

    $officeStmt = $db->prepare(
        'INSERT INTO offices (name, description, active)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE name = name'
    );

    foreach ($offices as [$name, $description]) {
        $officeStmt->execute([$name, $description]);
    }

    $steps = [
        [1, 'Librarian', 'Librarian'],
        [2, 'Sports and Games (Matron)', 'Sports and Games (Matron)'],
        [3, 'Computer Lab/Room in Charge', 'Computer Lab/Room in Charge'],
        [4, 'Supplies Officer', 'Supplies Officer'],
        [5, 'Transport Officer', 'Transport Officer'],
        [6, 'Dispensary', 'Dispensary'],
        [7, 'Head of Department', 'Head of Department'],
        [8, 'Hostels Superintendent’s Report (Accommodation Officer)', 'Hostels Superintendent (Accommodation Officer)'],
        [9, 'Director of Student Affairs', 'Director of Student Affairs'],
        [10, 'Admissions Officer', 'Admissions Officer'],
        [11, 'Director of Finance and Accounts', 'Director of Finance and Accounts'],
    ];

    $stepStmt = $db->prepare(
        'INSERT INTO workflow_steps (step_number, title, office_id)
         VALUES (?, ?, (SELECT id FROM offices WHERE name = ? LIMIT 1))
         ON DUPLICATE KEY UPDATE step_number = step_number'
    );

    foreach ($steps as [$number, $title, $office]) {
        $stepStmt->execute([$number, $title, $office]);
    }
}

function seed_demo_accounts(PDO $db): void
{
    $roles = [];
    foreach ($db->query('SELECT id, name FROM roles') as $row) {
        $roles[$row['name']] = (int) $row['id'];
    }

    $departmentId = (int) $db->query(
        "SELECT id FROM departments WHERE code = 'EPM' LIMIT 1"
    )->fetchColumn();

    $officeIds = [];
    foreach ($db->query('SELECT id, name FROM offices') as $row) {
        $officeIds[$row['name']] = (int) $row['id'];
    }

    $accounts = [
        ['admin', 'System Administrator', 'Admin@IRDP2026', 'ADMIN', null, null],
        ['LIB001', 'Joseph Mrema', 'Mrema@2026', 'OFFICER', null, 'Librarian'],
        ['SPORT001', 'Rehema John', 'John@2026', 'OFFICER', null, 'Sports and Games (Matron)'],
        ['COMP001', 'Daniel Massawe', 'Massawe@2026', 'OFFICER', null, 'Computer Lab/Room in Charge'],
        ['SUP001', 'Grace Mallya', 'Mallya@2026', 'OFFICER', null, 'Supplies Officer'],
        ['TRANS001', 'Peter Kweka', 'Kweka@2026', 'OFFICER', null, 'Transport Officer'],
        ['DISP001', 'Asha Mushi', 'Mushi@2026', 'OFFICER', null, 'Dispensary'],
        ['EPM001', 'Michael Mgimwa', 'Mgimwa@2026', 'OFFICER', null, 'Head of Department'],
        ['HOSTEL001', 'John Mushi', 'Mushi@2026', 'OFFICER', null, 'Hostels Superintendent (Accommodation Officer)'],
        ['DSA001', 'Fatuma Said', 'Said@2026', 'OFFICER', null, 'Director of Student Affairs'],
        ['ADM001', 'Robert Kimaro', 'Kimaro@2026', 'OFFICER', null, 'Admissions Officer'],
        ['FIN001', 'David Mollel', 'Mollel@2026', 'OFFICER', null, 'Director of Finance and Accounts'],
    ];

    $findUser = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $insertUser = $db->prepare(
        'INSERT INTO users (username, password_hash, full_name, role_id, department_id, active)
         VALUES (?, ?, ?, ?, ?, 1)'
    );

    foreach ($accounts as [$username, $fullName, $password, $role, $programme, $office]) {
        $findUser->execute([$username]);
        $userId = (int) ($findUser->fetchColumn() ?: 0);
        $newUser = $userId === 0;

        if ($userId === 0) {
            $departmentForUser = $role === 'OFFICER' && $office === 'Head of Department'
                ? $departmentId
                : null;

            $insertUser->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $fullName,
                $roles[$role],
                $departmentForUser,
            ]);

            $userId = (int) $db->lastInsertId();
        }

        if ($role === 'STUDENT') {
            $studentExists = $db->prepare(
                'SELECT id FROM students WHERE user_id = ? LIMIT 1'
            );
            $studentExists->execute([$userId]);

            if (!$studentExists->fetchColumn()) {
                $db->prepare(
                    'INSERT INTO students
                     (user_id, registration_number, programme, year_of_study, department_id)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $userId,
                    $username,
                    $programme,
                    '2025/2026',
                    $departmentId,
                ]);
            }
        }

        if ($newUser && $role === 'OFFICER' && isset($officeIds[$office])) {
            $db->prepare(
                'INSERT IGNORE INTO user_offices (user_id, office_id) VALUES (?, ?)'
            )->execute([$userId, $officeIds[$office]]);
        }
    }

    $demoStudents=require __DIR__.'/../config/demo_students.php';
    if(count($demoStudents)!==15){throw new RuntimeException('The demonstration must define exactly fifteen seeded students.');}
    $findStudentUser=$db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
    $insertStudentUser=$db->prepare('INSERT INTO users(username,password_hash,full_name,role_id,department_id,active,force_password_change) VALUES (?,?,?,?,?,1,0)');
    $updateLegacyUser=$db->prepare('UPDATE users SET username=?,password_hash=?,full_name=?,role_id=?,department_id=?,active=1,force_password_change=0,auth_version=auth_version+1 WHERE id=?');
    $updateStudent=$db->prepare('UPDATE students SET registration_number=?,programme=?,year_of_study=?,department_id=? WHERE user_id=?');
    foreach($demoStudents as $demo){
        $registration=$demo['registration_number'];
        $password=demo_student_initial_password($demo);
        $findStudentUser->execute([$registration]);
        $userId=(int)($findStudentUser->fetchColumn()?:0);
        $legacyUpgrade=false;
        if(!$userId && $demo['legacy_registration']){
            $findStudentUser->execute([$demo['legacy_registration']]);
            $userId=(int)($findStudentUser->fetchColumn()?:0);
            $legacyUpgrade=$userId>0;
        }
        if(!$userId){
            $insertStudentUser->execute([$registration,demo_student_password_hash($password),$demo['full_name'],$roles['STUDENT'],$departmentId]);
            $userId=(int)$db->lastInsertId();
        } elseif($legacyUpgrade){
            $updateLegacyUser->execute([$registration,demo_student_password_hash($password),$demo['full_name'],$roles['STUDENT'],$departmentId,$userId]);
        }
        $student=$db->prepare('SELECT id FROM students WHERE user_id=?');
        $student->execute([$userId]);
        if($student->fetchColumn()){
            $updateStudent->execute([$registration,$demo['programme'],$demo['academic_cycle'],$departmentId,$userId]);
        } else {
            $db->prepare('INSERT INTO students(user_id,registration_number,programme,year_of_study,department_id) VALUES (?,?,?,?,?)')->execute([$userId,$registration,$demo['programme'],$demo['academic_cycle'],$departmentId]);
        }
    }
}

function demo_student_initial_password(array $student): string
{
    $parts=preg_split('/\s+/u',trim((string)$student['full_name']));
    $surname=mb_strtoupper((string)end($parts),'UTF-8');
    if(!preg_match('/([0-9]{4})$/D',(string)$student['registration_number'],$match)){throw new RuntimeException('Demo registration number needs four trailing digits.');}
    return $surname.$match[1];
}

function demo_student_password_hash(string $password): string
{
    if(!defined('PASSWORD_ARGON2ID')){throw new RuntimeException('Demo password storage requires PHP Argon2id support.');}
    $hash=password_hash($password,PASSWORD_ARGON2ID,['memory_cost'=>19456,'time_cost'=>2,'threads'=>1]);
    if(!is_string($hash)){throw new RuntimeException('Demo password could not be securely hashed.');}
    return $hash;
}
