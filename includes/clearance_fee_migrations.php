<?php
declare(strict_types=1);

/** Add entry-fee records without changing any existing clearance request or stage. */
function migrate_clearance_fees(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS clearance_fee_rates (
        cycle_id INT PRIMARY KEY, amount DECIMAL(14,2) NOT NULL,
        updated_by INT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(cycle_id) REFERENCES academic_cycles(id), FOREIGN KEY(updated_by) REFERENCES users(id)
    ) ENGINE=InnoDB');
    $db->exec('CREATE TABLE IF NOT EXISTS clearance_fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, cycle_id INT NOT NULL,
        assigned_officer_id INT NOT NULL, amount DECIMAL(14,2) NOT NULL,
        status ENUM("REQUESTED","AWAITING_PAYMENT","AWAITING_REVIEW","APPROVED") NOT NULL DEFAULT "REQUESTED",
        control_number VARCHAR(30) NULL, payment_version INT NOT NULL DEFAULT 1, receipt_id INT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_by INT NULL, approved_at DATETIME NULL,
        UNIQUE KEY unique_student_fee_cycle(student_id,cycle_id),
        INDEX fee_queue(assigned_officer_id,status,id),
        FOREIGN KEY(student_id) REFERENCES students(id), FOREIGN KEY(cycle_id) REFERENCES academic_cycles(id),
        FOREIGN KEY(assigned_officer_id) REFERENCES users(id), FOREIGN KEY(approved_by) REFERENCES users(id)
    ) ENGINE=InnoDB');
    $db->exec('CREATE TABLE IF NOT EXISTS clearance_fee_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY, payment_id INT NOT NULL, payment_version INT NOT NULL,
        control_number VARCHAR(30) NOT NULL, uploader_id INT NOT NULL,
        stored_name VARCHAR(80) NOT NULL, original_name VARCHAR(180) NOT NULL,
        mime_type VARCHAR(100) NOT NULL, byte_size INT NOT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(payment_id) REFERENCES clearance_fee_payments(id), FOREIGN KEY(uploader_id) REFERENCES users(id)
    ) ENGINE=InnoDB');
    $db->exec('CREATE TABLE IF NOT EXISTS clearance_fee_actions (
        id INT AUTO_INCREMENT PRIMARY KEY, payment_id INT NOT NULL, actor_id INT NOT NULL,
        action VARCHAR(40) NOT NULL, details_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(payment_id) REFERENCES clearance_fee_payments(id), FOREIGN KEY(actor_id) REFERENCES users(id)
    ) ENGINE=InnoDB');
}
