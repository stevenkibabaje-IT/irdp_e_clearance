<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
$actor=ar_ui_require_role('STUDENT');$error='';$errors=[];
try{$stageId=positive_id($_GET['stage']??$_POST['stage_id']??null,'stage_id');$stage=ar_row($pdo,"SELECT s.*,r.student_user_id FROM clearance_stages s JOIN clearance_requests r ON r.id=s.request_id WHERE s.id=? AND s.row_kind='INSTANCE'",[$stageId]);if(!$stage||(int)$stage['student_user_id']!==(int)$actor['id']){throw new DomainException('Stage not found.');}}catch(Throwable $e){http_response_code(404);exit('Stage not found.');}
$isFinance=(int)$stage['step_number']===11;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  ar_ui_csrf();$mode=text_input($_POST,'mode',20);$files=upload_file_list($_FILES['evidence']??[]);
  $response=$isFinance?'Payment receipt submitted for the assigned Finance control number.':text_input($_POST,'response',2000);
  if(($isFinance&&$mode!=='finance_payment')||(!$isFinance&&$mode!=='resubmit')){throw new DomainException('Invalid response action.');}
  ai_resubmit($pdo,(int)$actor['id'],$stageId,(int)($_POST['row_version']??-1),$response,$files,$isFinance?finance_control_number($_POST['control_number']??null):null);
  flash('success',$isFinance?'Payment receipt submitted. Awaiting Finance approval.':'Your response and evidence were submitted to the responsible office.');
  redirect('student/status.php#stage-'.$stageId);
 }catch(Throwable $e){$error=page_error($e,$errors);$stage=ar_row($pdo,"SELECT s.*,r.student_user_id FROM clearance_stages s JOIN clearance_requests r ON r.id=s.request_id WHERE s.id=?",[$stageId]);}
}
$events=ai_rows($pdo,"SELECT * FROM activity_log WHERE stage_id=? AND family IN ('STAGE_DECISION','REVIEW_CYCLE') ORDER BY id",[$stageId]);
$cycles=[];for($number=1;$number<=(int)$stage['review_cycle'];$number++){$cycles[$number]=['cycle_number'=>$number,'opened_at'=>$stage['actionable_at'],'closed_at'=>null,'student_response'=>''];}
$actions=[];foreach($events as $event){$number=max(1,(int)($event['review_cycle']?:1));if(!isset($cycles[$number])){$cycles[$number]=['cycle_number'=>$number,'opened_at'=>$event['created_at'],'closed_at'=>null,'student_response'=>''];}if($event['family']==='REVIEW_CYCLE'&&$event['event_type']==='RESUBMITTED'){$cycles[$number]['student_response']=$event['reason']??'';}if($event['family']==='STAGE_DECISION'){$payload=json_decode($event['payload']??'{}',true)?:[];$actions[]=['review_cycle'=>$number,'action'=>$event['event_type'],'created_at'=>$event['created_at'],'comments'=>$event['reason']??'','corrective_instructions'=>$payload['instructions']??''];}}
$evidence=ai_rows($pdo,"SELECT id,review_cycle cycle_number,original_name FROM documents WHERE stage_id=? AND kind IN ('STAGE_EVIDENCE','FINANCE_RECEIPT') ORDER BY id",[$stageId]);
$pageTitle=$isFinance?'Finance Payment Receipt':'Response and Evidence';require_once __DIR__.'/../includes/header.php';
?><section class="panel">
    <h1><?= e($stage['title']) ?></h1>
    <p>Decision: <?= e($stage['status']) ?></p>
    <?php if ($error): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($isFinance): ?>
        <?php finance_payment_form($stage,$errors,true); ?>
        <a class="btn secondary" href="<?= e(url('student/status.php#stage-'.$stageId)) ?>">Back to My Clearance</a>
    <?php elseif ($stage['status']==='REJECTED'): ?>
        <div class="comment rejection">
            <strong>Rejection reason:</strong> <?= e($stage['comments']) ?><br>
            <strong>Corrective instructions:</strong> <?= e($stage['corrective_instructions'] ?: 'Contact this office to confirm the correction required.') ?>
        </div>
        <?php student_evidence_form($pdo,$stage,$errors); ?>
    <?php else: ?>
        <p><?= $stage['status']==='APPROVED' ? 'This office has approved your clearance. Track the remaining offices in My Clearance.' : 'Your stage is awaiting office review. Other offices can continue reviewing independently.' ?></p>
        <a class="btn primary" href="<?= e(url('student/status.php#stage-'.$stageId)) ?>">Back to My Clearance</a>
    <?php endif; ?>
</section>
<section class="panel">
    <h2>Submitted evidence and review history</h2>
    <?php foreach ($cycles as $cycle): ?>
        <article class="comment">
            <h3>Review cycle <?= (int)$cycle['cycle_number'] ?></h3>
            <p>Opened <?= e($cycle['opened_at'] ?: 'Not yet actionable') ?> &middot; Closed <?= e($cycle['closed_at'] ?: 'Awaiting decision') ?></p>
            <?php if ($cycle['student_response']): ?><p>Student response: <?= e($cycle['student_response']) ?></p><?php endif; ?>
            <?php foreach ($actions as $action): if ((int)$action['review_cycle']!==(int)$cycle['cycle_number']) { continue; } ?>
                <p><strong><?= e($action['action']) ?></strong> &middot; <?= e($action['created_at']) ?><br><?= e($action['comments']) ?><br>Instructions: <?= e($action['corrective_instructions']) ?></p>
            <?php endforeach; ?>
            <?php foreach ($evidence as $file): if ((int)$file['cycle_number']!==(int)$cycle['cycle_number']) { continue; } ?>
                <p><a href="<?= e(url('files/evidence.php?id='.$file['id'])) ?>"><?= e($file['original_name']) ?></a></p>
            <?php endforeach; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
