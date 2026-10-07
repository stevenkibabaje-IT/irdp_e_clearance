<?php
declare(strict_types=1);

/** Open unfinished office reviews without changing recorded decisions or documents. */
function migrate_parallel_clearance(PDO $db): void
{
    if ($db->query("SELECT version FROM schema_migrations WHERE version='2026_parallel_clearance_v1'")->fetchColumn()) {
        return;
    }

    // Retain legacy enum values so historical and cancelled records remain readable.
    $db->exec("ALTER TABLE clearance_stages ALTER COLUMN status SET DEFAULT 'PENDING'");
    $db->beginTransaction();
    try {
        // Match the request-before-stage lock order used by office decisions.
        $db->query('SELECT id FROM clearance_requests WHERE status IN ("IN_PROGRESS","PAUSED") ORDER BY id FOR UPDATE')->fetchAll();
        $db->exec('UPDATE clearance_stages cs INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
            SET cs.status="PENDING",cs.started_at=COALESCE(cs.started_at,NOW()),cs.actionable_at=COALESCE(cs.actionable_at,NOW())
            WHERE cr.status IN ("IN_PROGRESS","PAUSED") AND cs.status="LOCKED"');
        $db->exec('INSERT IGNORE INTO review_cycles(stage_id,cycle_number,opened_at)
            SELECT cs.id,cs.review_cycle,cs.actionable_at FROM clearance_stages cs
            INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
            WHERE cr.status IN ("IN_PROGRESS","PAUSED") AND cs.status IN ("PENDING","IN_REVIEW")');
        $db->exec('UPDATE review_cycles rc INNER JOIN clearance_stages cs ON cs.id=rc.stage_id AND cs.review_cycle=rc.cycle_number
            INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
            SET rc.opened_at=COALESCE(rc.opened_at,cs.actionable_at)
            WHERE cr.status IN ("IN_PROGRESS","PAUSED") AND cs.status IN ("PENDING","IN_REVIEW")');
        $db->exec('UPDATE clearance_requests cr SET status=CASE WHEN EXISTS (
            SELECT 1 FROM clearance_stages cs WHERE cs.clearance_request_id=cr.id AND cs.status="REJECTED"
            ) THEN "PAUSED" ELSE "IN_PROGRESS" END WHERE cr.status IN ("IN_PROGRESS","PAUSED")');
        $db->exec("INSERT INTO schema_migrations(version) VALUES ('2026_parallel_clearance_v1')");
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}
