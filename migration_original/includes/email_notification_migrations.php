<?php
declare(strict_types=1);

function migrate_email_notifications(PDO $db): void
{
    $version = '2026_email_notifications_v1';
    $s = $db->prepare('SELECT version FROM schema_migrations WHERE version=?');
    $s->execute([$version]);
    if ($s->fetchColumn()) { return; }
    $db->exec('CREATE TABLE IF NOT EXISTS email_outbox (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL UNIQUE,
        recipient VARCHAR(160) NOT NULL,
        status ENUM("PENDING","PROCESSING","RETRY","SENT","FAILED","CANCELLED") NOT NULL DEFAULT "PENDING",
        attempts INT NOT NULL DEFAULT 0,
        available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_attempt_at DATETIME NULL,
        sent_at DATETIME NULL,
        last_error VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_ready(status,available_at,id),
        FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB');
    $db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES (?)')->execute([$version]);
}
