<?php
declare(strict_types=1);

/** Preserve historical decisions while reopening active legacy Finance payment requests. */
function migrate_finance_payment_requests(PDO $db): void
{
    if ($db->query("SELECT version FROM schema_migrations WHERE version='2026_finance_payment_requests_v1'")->fetchColumn()) { return; }
    $type = $db->query("SHOW COLUMNS FROM stage_actions LIKE 'action'")->fetch();
    if (!str_contains((string)$type['Type'], "'PAYMENT_REQUESTED'")) {
        $db->exec("ALTER TABLE stage_actions MODIFY COLUMN action ENUM('APPROVED','REJECTED','PAYMENT_REQUESTED') NOT NULL");
    }
    $db->beginTransaction();
    try {
        $db->query('SELECT id FROM clearance_requests WHERE status IN ("IN_PROGRESS","PAUSED") ORDER BY id FOR UPDATE')->fetchAll();
        $stages = $db->query('SELECT cs.* FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id
            INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
            WHERE ws.step_number=11 AND cs.status="REJECTED" AND cr.status IN ("IN_PROGRESS","PAUSED") ORDER BY cs.id FOR UPDATE')->fetchAll();
        foreach ($stages as $stage) {
            $details = json_decode((string)($stage['details_json'] ?? ''), true);
            // A legacy Finance finding without a number needs the officer to issue one.
            // Existing control numbers and receipt metadata stay in their recorded cycles.
            if (is_array($details) && preg_match('/^[0-9]{6,30}$/D', (string)($details['control_number'] ?? ''))) {
                $details['payment_status'] = 'AWAITING_PAYMENT';
                $db->prepare('UPDATE clearance_stages SET details_json=? WHERE id=?')->execute([json_encode($details, JSON_THROW_ON_ERROR),$stage['id']]);
            }
            $db->prepare('UPDATE review_cycles SET closed_at=COALESCE(closed_at,NOW()) WHERE stage_id=? AND cycle_number=?')->execute([$stage['id'],$stage['review_cycle']]);
            $nextCycle = (int)$stage['review_cycle'] + 1;
            $db->prepare('INSERT INTO review_cycles(stage_id,cycle_number,opened_at) VALUES (?,?,NOW())')->execute([$stage['id'],$nextCycle]);
            $db->prepare('UPDATE clearance_stages SET status="PENDING",review_cycle=?,reviewed_at=NULL,actionable_at=NOW() WHERE id=?')->execute([$nextCycle,$stage['id']]);
        }
        $db->exec('UPDATE clearance_requests cr SET status=CASE WHEN EXISTS (
            SELECT 1 FROM clearance_stages cs WHERE cs.clearance_request_id=cr.id AND cs.status="REJECTED"
            ) THEN "PAUSED" ELSE "IN_PROGRESS" END WHERE cr.status IN ("IN_PROGRESS","PAUSED")');
        $db->exec("INSERT INTO schema_migrations(version) VALUES ('2026_finance_payment_requests_v1')");
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}
