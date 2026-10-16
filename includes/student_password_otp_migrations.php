<?php
declare(strict_types=1);

/** Add recovery challenges without changing accounts or existing reset history. */
function migrate_student_password_otps(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS student_password_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_hash CHAR(64) NOT NULL UNIQUE,
        otp_hash VARCHAR(255) NOT NULL,
        email VARCHAR(160) NOT NULL,
        auth_version INT NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        delivered_at DATETIME NULL,
        used_at DATETIME NULL,
        reset_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_otp_user(user_id,used_at,created_at),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(reset_id) REFERENCES password_resets(id)
    ) ENGINE=InnoDB');
}
