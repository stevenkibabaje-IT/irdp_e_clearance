<?php
declare(strict_types=1);

/** Start one request per academic cycle with all office reviews available. */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');

$u = current_user();
$period=clearance_period_state($pdo);
$existing = $period['academic_cycle'] ? get_clearance_for_cycle($pdo, (int)$u['student_id'], (string)$period['academic_cycle']) : null;

if ($existing) {
    redirect('student/status.php');
}

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        require_clearance_period($pdo);

        $academicYear = existing_academic_cycle($pdo, text_input($_POST, 'academic_year', 20));
        require_clearance_period($pdo, $academicYear);

        $pdo->beginTransaction();

        // Serialize starts for the same student, including simultaneous form submissions.
        $studentLock = $pdo->prepare('SELECT id FROM students WHERE id = ? FOR UPDATE');
        $studentLock->execute([(int) $u['student_id']]);
        if (!$studentLock->fetchColumn()) {
            throw new RuntimeException('Student account not found.');
        }
        if (get_clearance_for_cycle($pdo, (int)$u['student_id'], $academicYear)) {
            throw new ValidationException(['academic_year'=>'A clearance request already exists for this cycle. Continue the existing clearance.']);
        }

        require_clearance_period($pdo, $academicYear, true);
        $entryFee = require_approved_clearance_fee($pdo, (int)$u['student_id'], $academicYear);

        $insertRequest = $pdo->prepare(
            'INSERT INTO clearance_requests (student_id, academic_year, status, started_at)
             VALUES (?, ?, "IN_PROGRESS", NOW())'
        );
        $insertRequest->execute([(int) $u['student_id'], $academicYear]);
        $requestId = (int) $pdo->lastInsertId();

        $studentDeptStmt = $pdo->prepare('SELECT department_id FROM students WHERE id = ? LIMIT 1');
        $studentDeptStmt->execute([(int) $u['student_id']]);
        $studentDepartmentId = (int) ($studentDeptStmt->fetchColumn() ?: 0);

        $steps = $pdo->query(
            'SELECT ws.* FROM workflow_steps ws INNER JOIN offices o ON o.id=ws.office_id
             WHERE ws.active = 1 AND o.active = 1
             ORDER BY step_number'
        )->fetchAll();

        if (array_map(static fn(array $step): int => (int) $step['step_number'], $steps) !== range(1, 11)) {
            throw new RuntimeException('The system must have exactly 11 active clearance stages before a request can start.');
        }

        $insertStage = $pdo->prepare(
            'INSERT INTO clearance_stages
             (clearance_request_id, workflow_step_id, office_id, assigned_officer_id, status, started_at, original_officer_id, actionable_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $startedAt = date('Y-m-d H:i:s');
        foreach ($steps as $step) {
            $departmentId = (int) $step['step_number'] === 7 ? $studentDepartmentId : 0;
            $officerId = find_office_reviewer($pdo, (int) $step['office_id'], $departmentId);
            if ($officerId === null) {
                throw new RuntimeException('No active officer is assigned to '.$step['title'].'. Contact the administrator.');
            }
            $insertStage->execute([
                $requestId,
                (int) $step['id'],
                (int) $step['office_id'],
                $officerId ? (int) $officerId : null,
                'PENDING',
                $startedAt,
                $officerId ? (int)$officerId : null,
                $startedAt,
            ]);
            $stageId = (int)$pdo->lastInsertId();
            ensure_review_cycle($pdo, $stageId);
            route_clearance_stage($pdo, $requestId, $stageId, (int) $u['id']);
            notify_stage($pdo, stage_context($pdo, $stageId), 'Clearance started', 'Your office review is ready. Each office can review independently.');
        }

        audit(
            $pdo,
            'CLEARANCE_STARTED',
            (int) $u['id'],
            $requestId,
            'Student started a new clearance request. Approved entry-fee payment '.$entryFee['id'].'.'
        );

        $pdo->commit();
        flash('success', 'Clearance started successfully. All 11 offices can now review their stages.');
        redirect('student/status.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ((int)($e->errorInfo[1]??0)===1062) {
            $errors=['academic_year'=>'A clearance request already exists for this cycle. Continue the existing clearance.'];
            $error=$errors['academic_year'];
        } else {
            $error=user_safe_error($e);
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = page_error($e, $errors);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'The clearance could not be started. Please contact the administrator.';
    }
}

$pageTitle = 'Start Clearance';
$entryFee = $period['cycle_id'] ? clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']) : null;
$feeApproved = $entryFee && $entryFee['status']==='APPROVED';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">NEW REQUEST</span>
        <h1>Start Clearance</h1>
        <p class="muted">All 11 offices can review independently. Clearance completes after every office approves.</p>
    </div>
</div>

<div class="panel" style="max-width:680px;">
    <h2>Clearance Details</h2>

    <?php if (!$period['is_open']): ?><div class="alert warning"><?= e(CLEARANCE_CLOSED_MESSAGE) ?></div><?php endif; ?>
    <?php if (!$feeApproved): ?><div class="alert info">Pay the clearance entry fee and wait for Finance approval before starting.</div><p><a class="btn primary" href="<?= e(url('student/clearance_fee.php')) ?>">Clearance Fee / Request control number</a></p><?php else: ?><div class="alert success">Entry fee approved: TSh <?= e(number_format((float)$entryFee['amount'],2)) ?>.</div><?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <?php form_fields(['academic_year'=>['label'=>'Select academic cycle','type'=>'select','required'=>true,'options'=>[''=>'Select'] + array_column($pdo->query('SELECT label FROM academic_cycles WHERE active=1 ORDER BY label DESC')->fetchAll(), 'label', 'label'),'value'=>$period['academic_cycle'] ?? date('Y').'/'.((int)date('Y')+1)]], $errors); ?>

        <div class="form-actions">
            <a class="btn secondary" href="<?= e(url('student/dashboard.php')) ?>">Cancel</a>
            <button class="btn primary" type="submit" <?= $period['is_open'] && $feeApproved?'':'disabled' ?>>Start Clearance</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
