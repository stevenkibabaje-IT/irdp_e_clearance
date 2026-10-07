-- Non-destructive base schema. Run jobs/migrate.php for current feature migrations.
CREATE DATABASE IF NOT EXISTS irdp_e_clearance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE irdp_e_clearance;

SET FOREIGN_KEY_CHECKS=0;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE IF NOT EXISTS roles (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(30) NOT NULL UNIQUE
);
INSERT IGNORE INTO roles(name) VALUES ('STUDENT'),('OFFICER'),('ADMIN');

CREATE TABLE IF NOT EXISTS departments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(180) NOT NULL UNIQUE,
 active TINYINT(1) NOT NULL DEFAULT 1
);
INSERT IGNORE INTO departments(code,name) VALUES ('EPM','Department of Environmental Planning and Management');

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
 FOREIGN KEY(role_id) REFERENCES roles(id),
 FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL
);

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
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS offices (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL UNIQUE,
 description VARCHAR(255) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO offices(name,description) VALUES
('Librarian','Library clearance'),
('Sports and Games (Matron)','Sports and games / matron clearance'),
('Computer Lab/Room in Charge','Computer lab or room clearance'),
('Supplies Officer','Supplies clearance'),
('Transport Officer','Transport clearance'),
('Dispensary','Dispensary clearance'),
('Head of Department','Departmental clearance'),
('Hostels Superintendent (Accommodation Officer)','Accommodation and hostel clearance'),
('Director of Student Affairs','Student affairs clearance'),
('Admissions Officer','Admissions clearance'),
('Director of Finance and Accounts','Final finance clearance');

CREATE TABLE IF NOT EXISTS user_offices (
 user_id INT NOT NULL,
 office_id INT NOT NULL,
 PRIMARY KEY(user_id,office_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(office_id) REFERENCES offices(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS workflow_steps (
 id INT AUTO_INCREMENT PRIMARY KEY,
 step_number INT NOT NULL UNIQUE,
 title VARCHAR(180) NOT NULL,
 office_id INT NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY(office_id) REFERENCES offices(id)
);

INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 1,'Librarian',id FROM offices WHERE name='Librarian';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 2,'Sports and Games (Matron)',id FROM offices WHERE name='Sports and Games (Matron)';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 3,'Computer Lab/Room in Charge',id FROM offices WHERE name='Computer Lab/Room in Charge';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 4,'Supplies Officer',id FROM offices WHERE name='Supplies Officer';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 5,'Transport Officer',id FROM offices WHERE name='Transport Officer';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 6,'Dispensary',id FROM offices WHERE name='Dispensary';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 7,'Head of Department',id FROM offices WHERE name='Head of Department';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 8,'Hostels Superintendent’s Report (Accommodation Officer)',id FROM offices WHERE name='Hostels Superintendent (Accommodation Officer)';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 9,'Director of Student Affairs',id FROM offices WHERE name='Director of Student Affairs';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 10,'Admissions Officer',id FROM offices WHERE name='Admissions Officer';
INSERT IGNORE INTO workflow_steps(step_number,title,office_id)
SELECT 11,'Director of Finance and Accounts',id FROM offices WHERE name='Director of Finance and Accounts';

CREATE TABLE IF NOT EXISTS clearance_requests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 academic_year VARCHAR(20) NOT NULL DEFAULT '2025/2026',
 status ENUM('NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'NOT_STARTED',
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 UNIQUE KEY unique_student_clearance_cycle(student_id,academic_year)
);

CREATE TABLE IF NOT EXISTS clearance_stages (
 id INT AUTO_INCREMENT PRIMARY KEY,
 clearance_request_id INT NOT NULL,
 workflow_step_id INT NOT NULL,
 office_id INT NOT NULL,
 assigned_officer_id INT NULL,
 status ENUM('LOCKED','PENDING','IN_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'LOCKED',
 started_at DATETIME NULL,
 reviewed_at DATETIME NULL,
 comments TEXT NULL,
 details_json LONGTEXT NULL,
 FOREIGN KEY(clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
 FOREIGN KEY(workflow_step_id) REFERENCES workflow_steps(id),
 FOREIGN KEY(office_id) REFERENCES offices(id),
 FOREIGN KEY(assigned_officer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS stage_actions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 stage_id INT NOT NULL,
 officer_id INT NOT NULL,
 action ENUM('APPROVED','REJECTED') NOT NULL,
 comments TEXT NULL,
 details_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(stage_id) REFERENCES clearance_stages(id) ON DELETE CASCADE,
 FOREIGN KEY(officer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notifications (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS certificates (
 id INT AUTO_INCREMENT PRIMARY KEY,
 clearance_request_id INT NOT NULL UNIQUE,
 certificate_number VARCHAR(80) NOT NULL UNIQUE,
 verification_code VARCHAR(80) NOT NULL UNIQUE,
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_logs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 clearance_request_id INT NULL,
 action VARCHAR(120) NOT NULL,
 details TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(clearance_request_id) REFERENCES clearance_requests(id) ON DELETE SET NULL
);
