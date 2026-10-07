<?php
declare(strict_types=1);

/** Immutable clearance transcripts released after all office approvals. */

function transcript_for_student(PDO $pdo, int $studentId, ?int $requestId = null): ?array
{
    $sql = 'SELECT * FROM transcripts WHERE student_id=?';
    $params = [$studentId];
    if ($requestId !== null) {
        $sql .= ' AND clearance_request_id=?';
        $params[] = $requestId;
    }
    $s = $pdo->prepare($sql . ' ORDER BY version DESC LIMIT 1');
    $s->execute($params);
    return $s->fetch() ?: null;
}

function transcript_available(PDO $pdo, int $studentId): bool
{
    $s = $pdo->prepare('SELECT status FROM clearance_requests WHERE student_id=? ORDER BY id DESC LIMIT 1');
    $s->execute([$studentId]);
    return $s->fetchColumn() === 'COMPLETED';
}

// Use the request lock before the student lock, matching office decisions.
function transcript_request(PDO $pdo, int $studentId, ?int $requestId = null): array
{
    if ($requestId === null) {
        $s = $pdo->prepare('SELECT id FROM clearance_requests WHERE student_id=? ORDER BY id DESC LIMIT 1');
        $s->execute([$studentId]);
        $requestId = (int)$s->fetchColumn();
    }
    $s = $pdo->prepare('SELECT * FROM clearance_requests WHERE id=? AND student_id=? FOR UPDATE');
    $s->execute([$requestId, $studentId]);
    $request = $s->fetch();
    if (!$request || $request['status'] !== 'COMPLETED') {
        throw new RuntimeException('Transcript access will be available after successful completion of the clearance process.');
    }
    if (!certificate_release_allowed($pdo, (int)$request['id'])) {
        throw new RuntimeException('All eleven office approvals and cleared liabilities are required for a clearance transcript.');
    }
    $s = $pdo->prepare('SELECT id FROM students WHERE id=? FOR UPDATE');
    $s->execute([$studentId]);
    return $request;
}

function transcript_office_approvals(PDO $pdo, int $requestId): array
{
    $s = $pdo->prepare('SELECT cs.id AS stage_id,ws.step_number,ws.title,o.name AS office,cs.status,
        a.action,a.created_at AS approved_at,a.officer_id,u.full_name AS officer_name
        FROM clearance_stages cs
        INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id
        INNER JOIN offices o ON o.id=cs.office_id
        LEFT JOIN stage_actions a ON a.id=(SELECT MAX(last_action.id) FROM stage_actions last_action WHERE last_action.stage_id=cs.id)
        LEFT JOIN users u ON u.id=a.officer_id
        WHERE cs.clearance_request_id=? ORDER BY ws.step_number');
    $s->execute([$requestId]);
    $approvals = $s->fetchAll();
    if (array_map(fn($row) => (int)$row['step_number'], $approvals) !== range(1, 11)) {
        throw new RuntimeException('All eleven office approvals are required for a clearance transcript.');
    }
    foreach ($approvals as &$approval) {
        if ($approval['status'] !== 'APPROVED' || $approval['action'] !== 'APPROVED'
            || !$approval['officer_name'] || !$approval['approved_at']) {
            throw new RuntimeException('An office approval record is missing or incomplete.');
        }
        $approval['stage_id'] = (int)$approval['stage_id'];
        $approval['step_number'] = (int)$approval['step_number'];
        $approval['officer_id'] = (int)$approval['officer_id'];
        unset($approval['action']);
    }
    unset($approval);
    return $approvals;
}

function create_transcript(PDO $pdo, int $studentId, int $actor, ?int $requestId = null): array
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $request = transcript_request($pdo, $studentId, $requestId);
        $student = get_student($pdo, $studentId);
        $s = $pdo->prepare('SELECT id,label FROM academic_cycles WHERE label=?');
        $s->execute([$request['academic_year']]);
        $cycle = $s->fetch();
        if (!$student || !$cycle) {
            throw new RuntimeException('Student or clearance academic cycle was not found.');
        }
        $approvals = transcript_office_approvals($pdo, (int)$request['id']);
        $previous = transcript_for_student($pdo, $studentId);
        $version = $previous ? (int)$previous['version'] + 1 : 1;
        $token = bin2hex(random_bytes(32));
        $number = 'IRDP/CLT/' . date('Y') . '/' . str_pad((string)$studentId, 5, '0', STR_PAD_LEFT) . '/' . str_pad((string)$version, 2, '0', STR_PAD_LEFT);
        $snapshot = [
            'student' => [
                'id' => (int)$student['id'],
                'name' => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'programme' => $student['programme'],
                'department' => $student['department'],
            ],
            'cycle' => $cycle['label'],
            'document_type' => 'CLEARANCE_TRANSCRIPT',
            'clearance' => [
                'request_id' => (int)$request['id'],
                'status' => $request['status'],
                'started_at' => $request['started_at'],
                'completed_at' => $request['completed_at'],
            ],
            'approvals' => $approvals,
        ];
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $s = $pdo->prepare('INSERT INTO transcripts(student_id,clearance_request_id,cycle_id,version,document_number,verification_token,snapshot_json,snapshot_sha256,result_source,generated_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$studentId, $request['id'], $cycle['id'], $version, $number, $token, $json, hash('sha256', $json), 'CLEARANCE', $actor]);
        $transcriptId = (int)$pdo->lastInsertId();
        audit($pdo, 'TRANSCRIPT_GENERATED', $actor, (int)$request['id'], 'Clearance document ' . $number . ' version ' . $version);
        $s = $pdo->prepare('SELECT * FROM transcripts WHERE id=?');
        $s->execute([$transcriptId]);
        $document = $s->fetch();
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $document;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ensure_transcript(PDO $pdo, int $studentId, int $actor, ?int $requestId = null): array
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $request = transcript_request($pdo, $studentId, $requestId);
        $document = transcript_for_student($pdo, $studentId, (int)$request['id']);
        // Replace a valid previous academic format with a new clearance-only version.
        // A revoked document always stays revoked.
        if ($document && $document['status'] === 'VALID' && $document['result_source'] !== 'CLEARANCE') {
            $s = $pdo->prepare('UPDATE transcripts SET status="REVOKED",revoked_by=?,revoked_at=NOW(),revocation_reason=? WHERE id=?');
            $s->execute([$actor, 'Replaced by a clearance transcript.', $document['id']]);
            audit($pdo, 'TRANSCRIPT_REVOKED', $actor, (int)$request['id'], 'Previous document ' . $document['document_number'] . ' replaced by clearance format.');
            $document = create_transcript($pdo, $studentId, $actor, (int)$request['id']);
        } elseif (!$document) {
            $document = create_transcript($pdo, $studentId, $actor, (int)$request['id']);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $document;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
