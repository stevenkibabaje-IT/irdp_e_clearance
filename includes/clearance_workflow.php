<?php
declare(strict_types=1);

/** Assigned office reviews, evidence resubmission and sequential clearance decisions. */
function user_record(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare('SELECT u.*, r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function office_authority(PDO $pdo, int $userId, int $officeId, int $departmentId = 0): bool {
    $s = $pdo->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id=u.role_id INNER JOIN user_offices uo ON uo.user_id=u.id INNER JOIN offices o ON o.id=uo.office_id WHERE u.id=? AND u.active=1 AND r.name IN ("OFFICER","SUPERVISOR") AND o.active=1 AND uo.office_id=? AND (?=0 OR u.department_id=?)');
    $s->execute([$userId,$officeId,$departmentId,$departmentId]);
    return (bool) $s->fetchColumn();
}
function stage_context(PDO $pdo, int $stageId, bool $lock = false): array {
    $s=$pdo->prepare('SELECT clearance_request_id FROM clearance_stages WHERE id=?');
    $s->execute([$stageId]);
    $request=$s->fetchColumn();
    if (!$request) {
        throw new RuntimeException('Stage not found.');
    }
    if ($lock) {
        $s=$pdo->prepare('SELECT id FROM clearance_requests WHERE id=? FOR UPDATE');
        $s->execute([$request]);
    }
    $s=$pdo->prepare('SELECT * FROM clearance_stages WHERE id=?'.($lock?' FOR UPDATE':''));
    $s->execute([$stageId]);
    $stage=$s->fetch();
    $s=$pdo->prepare('SELECT cr.status AS request_status, cr.student_id, s.user_id AS student_user_id,s.department_id, ws.step_number, ws.title FROM clearance_requests cr INNER JOIN students s ON s.id=cr.student_id INNER JOIN workflow_steps ws ON ws.id=? WHERE cr.id=?');
    $s->execute([$stage['workflow_step_id'],$request]);
    return array_merge($stage,$s->fetch());
}
function stage_department(array $stage): int {
    return (int)$stage['step_number']===7 ? (int)($stage['department_id']??0) : 0;
}
function reviewer_assignment_valid(PDO $pdo, array $stage, int $reviewer): bool {
    return $reviewer > 0 && office_authority($pdo, $reviewer, (int)$stage['office_id'], stage_department($stage));
}
function stage_recipients(PDO $pdo, array $stage): array {
    $ids=[(int)$stage['student_user_id']];
    if ($stage['assigned_officer_id']) {
        $ids[]=(int)$stage['assigned_officer_id'];
    }
    foreach($pdo->query('SELECT u.id FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name="ADMIN" AND u.active=1') as $row) {
        $ids[]=(int)$row['id'];
    }
    return array_unique($ids);
}
function notify_stage(PDO $pdo, array $stage, string $title, string $message): void {
    foreach(stage_recipients($pdo,$stage) as $id) {
        notify($pdo,$id,$title,$message);
    }
}
function ensure_review_cycle(PDO $pdo, int $stageId): void {
    $pdo->prepare('INSERT IGNORE INTO review_cycles(stage_id,cycle_number,opened_at) SELECT id,review_cycle,actionable_at FROM clearance_stages WHERE id=?')->execute([$stageId]);
    $pdo->prepare('UPDATE review_cycles rc INNER JOIN clearance_stages cs ON cs.id=rc.stage_id AND cs.review_cycle=rc.cycle_number SET rc.opened_at=COALESCE(rc.opened_at,cs.actionable_at) WHERE cs.id=?')->execute([$stageId]);
}
function resubmit_stage(PDO $pdo, int $stageId, int $studentUser, string $response, array $files, ?string $controlNumber = null): void {
    $response=text_input(['response'=>$response],'response',4000,false);
    if(count($files)>5){throw new ValidationException(['evidence'=>'Upload at most five evidence files per resubmission.']);}
    $stored=[];
    $pdo->beginTransaction();
    try {
        $stage=stage_context($pdo,$stageId,true);
        if ((int)$stage['student_user_id']!==$studentUser || $stage['status']!=='REJECTED' || $stage['request_status']!=='PAUSED') {
            throw new RuntimeException('Only your rejected stage can be resubmitted.');
        }
        require_prerequisites($pdo,$stage);
        $isFinance = (int)$stage['step_number'] === 11;
        $payment = finance_payment_details($stage);
        if ($isFinance) {
            $issuedNumber = finance_control_number($payment['control_number'] ?? null);
            if ($controlNumber === null || finance_control_number($controlNumber) !== $issuedNumber) {
                throw new ValidationException(['control_number'=>'The control number changed. Refresh and upload the receipt for the current number.']);
            }
            if (count($files) !== 1) {
                throw new ValidationException(['evidence'=>'Upload one payment receipt as a PDF, JPG or PNG file.']);
            }
            $response = 'Payment receipt submitted for control number '.$issuedNumber.'.';
        } else {
            $response = text_input(['response'=>$response], 'response', 4000, true);
        }
        $cycle=(int)$stage['review_cycle']+1;
        $pdo->prepare('INSERT INTO review_cycles(stage_id,cycle_number,opened_at,student_response) VALUES (?,?,NOW(),?)')->execute([$stageId,$cycle,$response]);
        $receiptId = null;
        foreach($files as $file) {
            $upload=store_evidence_upload($file);
            $stored[]=$upload['stored_name'];
            $pdo->prepare('INSERT INTO stage_evidence(stage_id,cycle_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,?,?,?,?,?,?)')->execute([$stageId,$cycle,$studentUser,$upload['stored_name'],$upload['original_name'],$upload['mime_type'],$upload['byte_size']]);
            $receiptId = (int)$pdo->lastInsertId();
        }
        if ($isFinance) {
            $payment = ['control_number'=>$issuedNumber, 'payment_status'=>'AWAITING_REVIEW',
                'receipt_control_number'=>$issuedNumber, 'receipt_cycle'=>$cycle, 'receipt_id'=>$receiptId];
            $pdo->prepare('UPDATE clearance_stages SET details_json=?,comments=?,corrective_instructions="" WHERE id=?')
                ->execute([json_encode($payment, JSON_THROW_ON_ERROR), 'Payment receipt submitted. Awaiting Finance verification.', $stageId]);
        }
        $pdo->prepare('UPDATE clearance_stages SET status="PENDING",review_cycle=?,resubmissions=resubmissions+1,actionable_at=NOW(),reviewed_at=NULL,assignment_status=CASE WHEN assigned_officer_id IS NULL THEN "UNASSIGNED" ELSE "ASSIGNED" END WHERE id=?')->execute([$cycle,$stageId]);
        $pdo->prepare('UPDATE clearance_requests SET status="IN_PROGRESS" WHERE id=?')->execute([$stage['clearance_request_id']]);
        route_clearance_stage($pdo,(int)$stage['clearance_request_id'],$stageId,$studentUser);
        $stage=stage_context($pdo,$stageId);
        notify_stage($pdo,$stage,$isFinance ? 'Payment receipt submitted' : 'Clearance stage resubmitted',
            $isFinance ? 'The student uploaded a payment receipt for control number '.$issuedNumber.'. Finance must verify it before approval.'
                : 'Student response and evidence are available for stage '.$stage['step_number'].', review cycle '.$cycle.'.');
        audit($pdo,'STAGE_RESUBMITTED',$studentUser,(int)$stage['clearance_request_id'],'Stage '.$stageId.' cycle '.$cycle.'; evidence files '.count($stored));
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach($stored as $name) {
            private_delete('evidence',$name);
        }
        throw $e;
    }
}
function require_prerequisites(PDO $pdo, array $stage): void {
    $s=$pdo->prepare('SELECT COUNT(*) FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id WHERE cs.clearance_request_id=? AND ws.step_number<? AND cs.status<>"APPROVED"');
    $s->execute([$stage['clearance_request_id'],$stage['step_number']]);
    if((int)$s->fetchColumn()) {
        throw new RuntimeException('Earlier stages must be approved first.');
    }
}
function liabilities_clear(array $details, int $step): bool {
    if ($step === 11) { return finance_details_clear($details); }
    try { $details=validate_review_details(review_fields($step), $details); }
    catch (ValidationException $e) { return false; }
    if(isset($details['decision']) && $details['decision']!=='CLEARED') {
        return false;
    }
    foreach(['amount','so_has_to_pay','nothing_amount'] as $field) {
        if(isset($details[$field]) && (float)$details[$field]>0) {
            return false;
        }
    }
    if($step===8) {
        foreach(['key','mattress','curtains','broom','bucket'] as $field) {
            if(($details[$field]??'')!=='AVAILABLE') {
                return false;
            }
        }
    }
    return true;
}
function review_fields(int $step): array {
    return match($step) {
        1,2,3,4,5,6 => [['amount','Amount due (Tsh)','number']],
        7 => [['decision','Departmental decision','decision'],['amount','Amount due (Tsh)','number']],
        8 => [['key','Key','asset'],['mattress','Mattress','asset'],['curtains','Curtains','asset'],['broom','Broom','asset'],['bucket','Bucket','asset'],['so_has_to_pay','Amount to pay (Tsh)','number'],['nothing_amount','Other amount due (Tsh)','number']],
        9,10 => [['decision','Clearance decision','decision']],
        11 => [['control_number','Control number','control_number']],
        default => throw new RuntimeException('Unsupported workflow stage.'),
    } ;
}
function certificate_release_allowed(PDO $pdo,int $requestId): bool {
    $s=$pdo->prepare('SELECT cs.id,cs.review_cycle,cs.status,cs.details_json,s.user_id AS student_user_id,ws.step_number FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id INNER JOIN students s ON s.id=cr.student_id WHERE cs.clearance_request_id=? ORDER BY ws.step_number');
    $s->execute([$requestId]);
    $rows=$s->fetchAll();
    if(array_map(fn($r)=>(int)$r['step_number'],$rows)!==range(1,11)) {
        return false;
    }
    foreach($rows as $row) {
        $details=json_decode((string)$row['details_json'],true);
        if($row['status']!=='APPROVED' || !liabilities_clear(is_array($details)?$details:[],(int)$row['step_number'])
            || ((int)$row['step_number'] === 11 && is_array($details) && array_key_exists('control_number', $details)
                && !finance_receipt_available($pdo, $row))) {
            return false;
        }
    }
    return true;
}
function process_stage_decision(PDO $pdo,int $stageId,int $reviewer,array $input,array $fields): void {
    $action=text_input($input,'action',20);
    $acceptEvidence=$action==='APPROVED_EVIDENCE';
    $acceptCorrection=$acceptEvidence || $action==='APPROVED_CORRECTION';
    if($acceptCorrection){$action='APPROVED';}
    $comments = '';
    $instructions = '';
    if(!in_array($action,['APPROVED','REJECTED'],true)) {
        throw new RuntimeException('Choose Approve or Reject.');
    }
    $cycle=positive_id($input['review_cycle']??null,'review_cycle');
    $pdo->beginTransaction();
    try {
        $stage=stage_context($pdo,$stageId,true);
        lock_review_stage($pdo,(int)$stage['clearance_request_id'],$stageId,$reviewer,(string)$stage['status'],
            (int)$stage['step_number'] === 11 && $action === 'REJECTED');
        if($cycle!==(int)$stage['review_cycle']) {
            throw new RuntimeException('This review cycle changed. Refresh before deciding.');
        }
        $isFinance = (int)$stage['step_number'] === 11;
        if ($isFinance) {
            // Finance always uses the server's control-number schema, even on a crafted request.
            $details = validate_review_details(review_fields(11), $input);
            $existing = finance_payment_details($stage);
            if ($action === 'APPROVED') {
                if (!finance_receipt_available($pdo, $stage)) {
                    throw new ValidationException(['control_number'=>'A payment receipt for this control number is required before approval.']);
                }
                if (($existing['control_number'] ?? null) !== $details['control_number']) {
                    throw new ValidationException(['control_number'=>'Reject with the new control number and request a new receipt before approving.']);
                }
                $details = $existing;
                $details['payment_status'] = 'APPROVED';
                $comments = 'Payment receipt verified for control number '.$details['control_number'].'.';
            } else {
                $details['payment_status'] = 'AWAITING_PAYMENT';
                $comments = 'Payment required for control number '.$details['control_number'].'.';
                $instructions = 'Pay using control number '.$details['control_number'].' and upload the payment receipt. If your previous receipt was rejected, contact Finance and upload a corrected receipt.';
            }
        } else {
            $details = validate_review_details($fields, $input);
            $comments = text_input($input,'comments',4000,$action==='REJECTED');
            $instructions = text_input($input,'corrective_instructions',4000,$action==='REJECTED');
        }
        if($acceptCorrection && !$isFinance) {
            $evidence=$pdo->prepare('SELECT COUNT(*) FROM stage_evidence WHERE stage_id=? AND cycle_number=?');
            $evidence->execute([$stageId,$cycle]);
            $hasFiles=(int)$evidence->fetchColumn()>0;
            $response=$pdo->prepare('SELECT student_response FROM review_cycles WHERE stage_id=? AND cycle_number=? AND closed_at IS NULL');
            $response->execute([$stageId,$cycle]);
            $hasResponse=trim((string)$response->fetchColumn())!=='';
            if((int)$stage['resubmissions']<1 || $cycle<2 || ($acceptEvidence ? !$hasFiles : (!$hasFiles && !$hasResponse))) {
                throw new RuntimeException($acceptEvidence
                    ? 'Evidence approval requires supporting files for the current student resubmission.'
                    : 'Correction approval requires a response or supporting files for the current student resubmission.');
            }
            // This explicit office decision confirms the student correction resolves
            // the requirements. The earlier rejection details remain in history.
            foreach($fields as [$key,$label,$type]) {
                if($type==='asset'){$details[$key]='AVAILABLE';}
                elseif($type==='decision'){$details[$key]='CLEARED';}
                elseif(in_array($key,['amount','so_has_to_pay','nothing_amount'],true)){$details[$key]='0';}
            }
            if($comments==='' || $comments===(string)$stage['comments']) {
                $comments=$hasFiles ? 'Evidence verified; office requirements resolved.' : 'Student correction verified; office requirements resolved.';
            }
            $instructions='';
        }
        if($action==='APPROVED'&&!liabilities_clear($details,(int)$stage['step_number'])) {
            throw new RuntimeException('Resolve outstanding liabilities and missing items before approval.');
        }
        if ($isFinance && $stage['status'] === 'REJECTED') {
            // A changed payment request gets a new cycle, preserving prior control numbers.
            $cycle++;
            $pdo->prepare('INSERT INTO review_cycles(stage_id,cycle_number,opened_at) VALUES (?,?,NOW())')->execute([$stageId,$cycle]);
            $pdo->prepare('UPDATE clearance_stages SET review_cycle=? WHERE id=?')->execute([$cycle,$stageId]);
        }
        ensure_review_cycle($pdo,$stageId);
        $pdo->prepare('UPDATE clearance_stages SET status=?,comments=?,corrective_instructions=?,details_json=?,reviewed_at=NOW(),actionable_at=NULL WHERE id=?')->execute([$action,$comments,$instructions,json_encode($details,JSON_THROW_ON_ERROR),$stageId]);
        $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,corrective_instructions,details_json,review_cycle) VALUES (?,?,?,?,?,?,?)')->execute([$stageId,$reviewer,$action,$comments,$instructions,json_encode($details,JSON_THROW_ON_ERROR),$cycle]);
        $pdo->prepare('UPDATE review_cycles SET closed_at=NOW() WHERE stage_id=? AND cycle_number=?')->execute([$stageId,$cycle]);
        if($action==='REJECTED') {
            $pdo->prepare('UPDATE clearance_requests SET status="PAUSED" WHERE id=?')->execute([$stage['clearance_request_id']]);
            notify_stage($pdo,$stage,$isFinance ? 'Finance payment required' : 'Clearance stage rejected',
                $isFinance ? $instructions : 'Stage '.$stage['step_number'].': '.$comments.' Corrective instructions: '.$instructions.'. Submit a response and evidence to reopen this stage.');
        } else {
            $s=$pdo->prepare('SELECT cs.id FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id WHERE cs.clearance_request_id=? AND ws.step_number=?');
            $s->execute([$stage['clearance_request_id'],(int)$stage['step_number']+1]);
            $next=$s->fetchColumn();
            if($next) {
                $s=$pdo->prepare('UPDATE clearance_stages SET status="PENDING",started_at=COALESCE(started_at,NOW()),actionable_at=NOW() WHERE id=? AND status="LOCKED"');
                $s->execute([$next]);
                if($s->rowCount()!==1) {
                    throw new RuntimeException('The next stage is not locked. Contact the administrator.');
                }
                ensure_review_cycle($pdo,(int)$next);
                route_clearance_stage($pdo,(int)$stage['clearance_request_id'],(int)$next,$reviewer);
                $nextStage=stage_context($pdo,(int)$next);
                notify_stage($pdo,$nextStage,'Clearance stage ready','Stage '.$nextStage['step_number'].' is now actionable for review.');
            } else {
                if((int)$stage['step_number']!==11 || !certificate_release_allowed($pdo,(int)$stage['clearance_request_id'])) {
                    throw new RuntimeException('All 11 stages and certificate release checks must pass.');
                }
                $pdo->prepare('UPDATE clearance_requests SET status="COMPLETED",completed_at=NOW() WHERE id=?')->execute([$stage['clearance_request_id']]);
                $certificate=issue_certificate($pdo,(int)$stage['clearance_request_id']);
                ensure_transcript($pdo,(int)$stage['student_id'], $reviewer, (int)$stage['clearance_request_id']);
                notify_stage($pdo,$stage,'Clearance completed','Certificate '.$certificate['certificate_number'].' is available.');
            }
        }
        audit($pdo,'STAGE_'.$action,$reviewer,(int)$stage['clearance_request_id'],'Stage '.$stageId.' review cycle '.$cycle.($acceptCorrection?'; officer verified student correction and confirmed office requirements resolved.':''));
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
