<?php
declare(strict_types=1);

/** Entry fee and Finance's later debt review are separate payment workflows. */
const DEFAULT_CLEARANCE_FEE = '10000.00';

function clearance_finance_office(PDO $pdo): int
{
    return (int)$pdo->query('SELECT office_id FROM workflow_steps WHERE step_number=11 AND active=1')->fetchColumn();
}
function clearance_fee_officer(PDO $pdo, int $actor): bool
{
    $office = clearance_finance_office($pdo);
    return $office > 0 && office_authority($pdo, $actor, $office);
}
function clearance_fee_amount(PDO $pdo, int $cycle): string
{
    $s=$pdo->prepare('SELECT amount FROM clearance_fee_rates WHERE cycle_id=?');
    $s->execute([$cycle]);
    return (string)($s->fetchColumn() ?: DEFAULT_CLEARANCE_FEE);
}
function set_clearance_fee_amount(PDO $pdo, int $actor, int $cycle, mixed $value): void
{
    if (!clearance_fee_officer($pdo,$actor)) { throw new RuntimeException('Only Finance and Accounting can set the clearance fee.'); }
    $amount=clearance_amount($value,'amount','Clearance fee');
    if ((float)$amount<=0) { throw new ValidationException(['amount'=>'Enter a fee greater than zero.']); }
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT id FROM academic_cycles WHERE id=? AND active=1 FOR UPDATE');$s->execute([$cycle]);
        if (!$s->fetchColumn()) { throw new RuntimeException('Choose an active academic cycle.'); }
        $before=clearance_fee_amount($pdo,$cycle);
        $pdo->prepare('INSERT INTO clearance_fee_rates(cycle_id,amount,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),updated_by=VALUES(updated_by),updated_at=NOW()')->execute([$cycle,$amount,$actor]);
        audit($pdo,'CLEARANCE_FEE_RATE_CHANGED',$actor,null,json_encode(['cycle_id'=>$cycle,'before'=>$before,'after'=>$amount],JSON_THROW_ON_ERROR));
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()){$pdo->rollBack();}throw $e; }
}
function clearance_fee_for_cycle(PDO $pdo, int $student, int $cycle): ?array
{
    $s=$pdo->prepare('SELECT * FROM clearance_fee_payments WHERE student_id=? AND cycle_id=?');$s->execute([$student,$cycle]);
    return $s->fetch() ?: null;
}
function clearance_fee_record(PDO $pdo, int $id, bool $lock=false): ?array
{
    $s=$pdo->prepare('SELECT fp.*,s.user_id AS student_user_id,s.registration_number,u.full_name,ac.label AS academic_cycle
        FROM clearance_fee_payments fp INNER JOIN students s ON s.id=fp.student_id
        INNER JOIN users u ON u.id=s.user_id INNER JOIN academic_cycles ac ON ac.id=fp.cycle_id
        WHERE fp.id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);return $s->fetch() ?: null;
}
function clearance_fee_history(PDO $pdo, int $id): array
{
    $s=$pdo->prepare('SELECT fa.*,u.full_name FROM clearance_fee_actions fa INNER JOIN users u ON u.id=fa.actor_id WHERE fa.payment_id=? ORDER BY fa.id');$s->execute([$id]);return $s->fetchAll();
}
function clearance_fee_audit(PDO $pdo,array $fee,int $actor,string $action): void
{
    $details=json_encode(['amount'=>$fee['amount'],'control_number'=>$fee['control_number'],'version'=>$fee['payment_version'],'receipt_id'=>$fee['receipt_id']],JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO clearance_fee_actions(payment_id,actor_id,action,details_json) VALUES (?,?,?,?)')->execute([$fee['id'],$actor,$action,$details]);
    audit($pdo,'ENTRY_FEE_'.$action,$actor,null,'Payment '.$fee['id'].'; '.$details);
}
function request_clearance_fee(PDO $pdo, int $actor): int
{
    $user=user_record($pdo,$actor);
    if (!$user || !(int)$user['active'] || $user['role_name']!=='STUDENT') { throw new RuntimeException('An active student account is required.'); }
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT id FROM students WHERE user_id=? FOR UPDATE');$s->execute([$actor]);$student=(int)$s->fetchColumn();
        if (!$student) { throw new RuntimeException('Student account not found.'); }
        $period=require_clearance_period($pdo,null,true);
        if (get_clearance_for_cycle($pdo,$student,$period['academic_cycle'])) { throw new RuntimeException('Your existing clearance continues without an entry-fee payment.'); }
        $existing=clearance_fee_for_cycle($pdo,$student,(int)$period['cycle_id']);
        if ($existing) { $pdo->commit();return (int)$existing['id']; }
        $reviewer=find_office_reviewer($pdo,clearance_finance_office($pdo));
        if (!$reviewer) { throw new RuntimeException('No active Finance officer is assigned. Contact the administrator.'); }
        // Serialize the quote with changes to Finance's fee rate.
        $s=$pdo->prepare('SELECT id FROM academic_cycles WHERE id=? FOR UPDATE');$s->execute([$period['cycle_id']]);
        $amount=clearance_fee_amount($pdo,(int)$period['cycle_id']);
        $pdo->prepare('INSERT INTO clearance_fee_payments(student_id,cycle_id,assigned_officer_id,amount) VALUES (?,?,?,?)')->execute([$student,$period['cycle_id'],$reviewer,$amount]);
        $id=(int)$pdo->lastInsertId();$fee=clearance_fee_record($pdo,$id);
        clearance_fee_audit($pdo,$fee,$actor,'REQUESTED');
        notify($pdo,$reviewer,'Clearance fee control number requested',$user['full_name'].' requested a clearance entry-fee control number. Open Clearance Fee Payments.');
        $pdo->commit();return $id;
    } catch(Throwable $e) { if($pdo->inTransaction()){$pdo->rollBack();}throw $e; }
}
function clearance_fee_receipt(PDO $pdo,array $fee): ?array
{
    if (!$fee['receipt_id'] || !$fee['control_number']) { return null; }
    $s=$pdo->prepare('SELECT * FROM clearance_fee_receipts WHERE id=? AND payment_id=? AND payment_version=? AND control_number=? AND uploader_id=?');
    $s->execute([$fee['receipt_id'],$fee['id'],$fee['payment_version'],$fee['control_number'],$fee['student_user_id']]);
    $file=$s->fetch();return $file && is_file(private_path('evidence',$file['stored_name'])) ? $file : null;
}
function clearance_fee_access(PDO $pdo,array $fee,int $actor): bool
{
    $user=user_record($pdo,$actor);
    return $user && (int)$user['active'] && ($user['role_name']==='ADMIN'
        || ($user['role_name']==='STUDENT' && (int)$fee['student_user_id']===$actor)
        || ((int)$fee['assigned_officer_id']===$actor && clearance_fee_officer($pdo,$actor)));
}
function process_clearance_fee(PDO $pdo,int $id,int $actor,array $input,array $files=[]): void
{
    $mode=choice_input($input['mode']??null,['CONTROL','RECEIPT','APPROVE'],'mode');
    $version=positive_id($input['payment_version']??null,'payment_version');$stored=null;
    $pdo->beginTransaction();
    try {
        $fee=clearance_fee_record($pdo,$id);
        if (!$fee) { throw new RuntimeException('Fee payment not found.'); }
        // Every payment mutation and Start Clearance takes this same lock first.
        $s=$pdo->prepare('SELECT id FROM students WHERE id=? FOR UPDATE');$s->execute([$fee['student_id']]);
        $fee=clearance_fee_record($pdo,$id,true);
        $user=user_record($pdo,$actor);
        if (!clearance_fee_access($pdo,$fee,$actor) || !$user) { throw new RuntimeException('Access denied.'); }
        if ($fee['status']==='APPROVED') { throw new RuntimeException('This fee is already approved and cannot be changed.'); }
        if ($version!==(int)$fee['payment_version']) { throw new RuntimeException('This payment changed. Refresh before continuing.'); }
        if ($mode==='RECEIPT') {
            if ($user['role_name']!=='STUDENT' || (int)$fee['student_user_id']!==$actor) { throw new RuntimeException('Only the student can upload the payment receipt.'); }
            require_clearance_period($pdo,$fee['academic_cycle'],true);
            if (!in_array($fee['status'],['AWAITING_PAYMENT','AWAITING_REVIEW'],true) || finance_control_number($input['control_number']??null)!==$fee['control_number']) {
                throw new RuntimeException('Upload a receipt for the current issued control number.');
            }
            if (count($files)!==1) { throw new ValidationException(['evidence'=>'Upload one payment receipt as PDF, JPG or PNG (up to 5 MB).']); }
            $upload=store_evidence_upload($files[0]);$stored=$upload['stored_name'];
            $version++;
            $pdo->prepare('INSERT INTO clearance_fee_receipts(payment_id,payment_version,control_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$version,$fee['control_number'],$actor,$upload['stored_name'],$upload['original_name'],$upload['mime_type'],$upload['byte_size']]);
            $receipt=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE clearance_fee_payments SET status="AWAITING_REVIEW",payment_version=?,receipt_id=? WHERE id=?')->execute([$version,$receipt,$id]);
            notify($pdo,(int)$fee['assigned_officer_id'],'Clearance fee receipt submitted','Verify the payment receipt for '.$fee['registration_number'].' in Clearance Fee Payments.');
            $action='RECEIPT_SUBMITTED';
        } else {
            if ((int)$fee['assigned_officer_id']!==$actor || !clearance_fee_officer($pdo,$actor)) { throw new RuntimeException('Only the assigned Finance officer can decide this payment.'); }
            if ($mode==='CONTROL') {
                $number=finance_control_number($input['control_number']??null);
                if ($number===$fee['control_number']) { throw new RuntimeException('This control number has already been sent. Verify the receipt or wait for payment.'); }
                $pdo->prepare('UPDATE clearance_fee_payments SET control_number=?,status="AWAITING_PAYMENT",payment_version=payment_version+1,receipt_id=NULL WHERE id=?')->execute([$number,$id]);
                notify($pdo,(int)$fee['student_user_id'],'Clearance fee control number','Pay TSh '.number_format((float)$fee['amount'],2).' using '.$number.' and upload the receipt under Clearance Fee.');
                $action='CONTROL_ISSUED';
            } else {
                if ($fee['status']!=='AWAITING_REVIEW' || !clearance_fee_receipt($pdo,$fee)) { throw new RuntimeException('A receipt for the current control number is required before approval.'); }
                if (($input['confirm_payment']??null)!=='1') { throw new RuntimeException('Confirm that you verified the payment before approving.'); }
                $pdo->prepare('UPDATE clearance_fee_payments SET status="APPROVED",approved_by=?,approved_at=NOW() WHERE id=?')->execute([$actor,$id]);
                notify($pdo,(int)$fee['student_user_id'],'Clearance fee approved','Your clearance fee payment is approved. You can now select Start Clearance.');
                $action='APPROVED';
            }
        }
        clearance_fee_audit($pdo,clearance_fee_record($pdo,$id),$actor,$action);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()){$pdo->rollBack();}
        if($stored!==null){private_delete('evidence',$stored);}throw $e;
    }
}
function require_approved_clearance_fee(PDO $pdo,int $student,string $cycle): array
{
    $s=$pdo->prepare('SELECT fp.*,s.user_id AS student_user_id FROM clearance_fee_payments fp
        INNER JOIN academic_cycles ac ON ac.id=fp.cycle_id INNER JOIN students s ON s.id=fp.student_id
        WHERE fp.student_id=? AND ac.label=? FOR UPDATE');$s->execute([$student,$cycle]);$fee=$s->fetch();
    if (!$fee || $fee['status']!=='APPROVED' || !$fee['approved_by'] || !$fee['approved_at'] || !clearance_fee_receipt($pdo,$fee)) {
        throw new RuntimeException('Pay the clearance entry fee and wait for Finance approval before starting clearance.');
    }
    return $fee;
}
