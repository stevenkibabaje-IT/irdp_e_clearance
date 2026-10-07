<?php
declare(strict_types=1);

/** Shared form rendering for CSRF fields, labels, errors and evidence resubmission controls. */
function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function form_fields(array $fields,array $errors=[]): void {
    foreach($fields as $key=>$field) {
        $type=$field['type']??'text';
        if ($key === 'full_name') { $field['data-person-name'] = 'true'; }
        if($type==='password' && ($field['autocomplete']??'')==='new-password') {
            // HTML maxlength counts UTF-16 units and would truncate valid Unicode.
            // Enforce the character limits on the server without cutting input.
            unset($field['minlength'],$field['maxlength']);
            $field['data-password-min'] = PASSWORD_MIN_LENGTH;
            $field['data-password-max'] = PASSWORD_MAX_LENGTH;
            if($key==='password') {$field['help']='Use '.PASSWORD_MIN_LENGTH.'-'.PASSWORD_MAX_LENGTH.' characters. Spaces and Unicode are allowed. Choose unrelated words; avoid common or repeated passwords.';}
        }
        $value=$type==='password'?'':($_POST[$key]??($field['value']??''));
        if(!is_string($value)&&!is_int($value)) {
            $value='';
        }
        echo '<label for="'.e($key).'">'.e($field['label']).'</label>';
        $attrs=' id="'.e($key).'" name="'.e($key).'"'.(!empty($field['required'])?' required':'');
        foreach(['minlength','maxlength','min','max','pattern','accept','step','autocomplete','data-person-name','data-password-min','data-password-max'] as $attribute) {
            if(isset($field[$attribute])) {
                $attrs.=' '.$attribute.'="'.e($field[$attribute]).'"';
            }
        }
        if(isset($errors[$key])) {
            $attrs.=' aria-invalid="true"';
        }
        $described=[];
        if(isset($field['help'])) {$described[]=$key.'-help';}
        if(isset($errors[$key])) {$described[]=$key.'-error';}
        if($described) {$attrs.=' aria-describedby="'.e(implode(' ',$described)).'"';}
        if($type==='select') {
            echo '<select'.$attrs.'>';
            foreach($field['options'] as $id=>$label) {
                echo '<option value="'.e($id).'"'.((string)$value===(string)$id?' selected':'').'>'.e($label).'</option>';
            }
            echo '</select>';
        }
        elseif($type==='textarea') {
            echo '<textarea'.$attrs.'>'.e($value).'</textarea>';
        }
        else {
            echo '<input type="'.e($type).'"'.$attrs.($type==='file'?'':' value="'.e($value).'"').(!empty($field['multiple'])?' multiple':'').'>';
        }
        form_error($key,$errors);
        if(isset($field['help'])) {
            echo '<small id="'.e($key).'-help" class="muted">'.e($field['help']).'</small>';
        }
    }
}
function option_rows(array $rows,string $label='name'): array {
    $options=[''=>'Select'];
    foreach($rows as $row) {
        $options[(int)$row['id']]=$row[$label];
    }
    return $options;
}

function student_evidence_form(PDO $pdo, array $stage, array $errors=[]): void {
    $stageId=(int)$stage['id'];
    $responseId='response-'.$stageId;
    $evidenceId='evidence-'.$stageId;
    ?>
    <div class="student-evidence-form">
        <h3>Upload evidence and submit to the office</h3>
        <p>Explain how you resolved the rejection and attach your supporting documents. This office will review your submission. Other offices can continue reviewing independently.</p>
        <form method="post" action="<?= e(url('student/resubmit.php?stage='.$stageId)) ?>" enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <input type="hidden" name="stage_id" value="<?= $stageId ?>">
            <input type="hidden" name="mode" value="resubmit">
            <label for="<?= $responseId ?>">Response / explanation</label>
            <textarea id="<?= $responseId ?>" name="response" required maxlength="4000" placeholder="Explain the correction and the evidence you are attaching."><?= old_value('response') ?></textarea>
            <?php form_error('response',$errors); ?>
            <label for="<?= $evidenceId ?>">Supporting evidence</label>
            <input id="<?= $evidenceId ?>" type="file" name="evidence[]" accept=".pdf,.jpg,.jpeg,.png" multiple aria-describedby="evidence-help-<?= $stageId ?>">
            <p id="evidence-help-<?= $stageId ?>" class="muted">PDF, JPG or PNG. Up to five files, 5 MB each. Choose the files, then click Submit evidence to office.</p>
            <?php form_error('evidence',$errors); ?>
            <button class="btn primary" type="submit"><?= icon('upload') ?> Submit evidence to office</button>
        </form>
    </div>
    <?php
}

function finance_payment_form(array $stage, array $errors = [], bool $allowReceiptReplacement = false): void {
    $payment = finance_payment_details($stage);
    $number = (string)($payment['control_number'] ?? '');
    if ($number === '') {
        echo '<p class="muted">Finance will provide your payment control number when it reviews your clearance.</p>';
        return;
    }
    $stageId = (int)$stage['id'];
    ?>
    <div class="student-evidence-form">
        <h3>Finance payment</h3>
        <p>Control number / Namba ya malipo: <strong><?= e($number) ?></strong></p>
        <?php form_error('control_number', $errors); ?>
        <?php if (in_array($stage['status'], ['PENDING','IN_REVIEW','REJECTED'], true) && (($payment['payment_status'] ?? '') === 'AWAITING_PAYMENT' || ($allowReceiptReplacement && ($payment['payment_status'] ?? '') === 'AWAITING_REVIEW'))): ?>
            <p><?= ($payment['payment_status'] ?? '') === 'AWAITING_REVIEW' ? 'Your receipt is awaiting Finance review. If you need to correct it, upload a replacement below. Previous receipts stay in the history.' : 'Pay using this control number, then upload your payment receipt. Finance will verify the payment before approving.' ?></p>
            <form method="post" action="<?= e(url('student/resubmit.php?stage='.$stageId)) ?>" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <input type="hidden" name="stage_id" value="<?= $stageId ?>">
                <input type="hidden" name="mode" value="finance_payment">
                <input type="hidden" name="control_number" value="<?= e($number) ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <label for="receipt-<?= $stageId ?>">Payment receipt / Risiti ya malipo</label>
                <input id="receipt-<?= $stageId ?>" type="file" name="evidence[]" accept=".pdf,.jpg,.jpeg,.png" required aria-describedby="receipt-help-<?= $stageId ?>">
                <p id="receipt-help-<?= $stageId ?>" class="muted">One PDF, JPG or PNG file, up to 5 MB.</p>
                <?php form_error('evidence', $errors); ?>
                <button class="btn primary" type="submit"><?= icon('upload') ?> <?= ($payment['payment_status'] ?? '') === 'AWAITING_REVIEW' ? 'Replace payment receipt' : 'Upload payment receipt' ?></button>
            </form>
        <?php elseif ($stage['status'] === 'APPROVED'): ?>
            <p class="alert success">Finance approved your payment receipt.</p>
        <?php else: ?>
            <p class="alert info">Your payment receipt has been submitted. Awaiting Finance review.</p>
            <?php if (($payment['payment_status'] ?? '') === 'AWAITING_REVIEW'): ?>
                <a href="<?= e(url('student/resubmit.php?stage='.$stageId)) ?>">View or replace payment receipt</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function page_error(Throwable $e,array &$errors): string {
    if($e instanceof ValidationException) {
        $errors=$e->errors;
    }
    return user_safe_error($e);
}
