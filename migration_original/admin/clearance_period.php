<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_role('ADMIN');$error='';$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){try{verify_csrf();update_clearance_period($pdo,$_POST,(int)current_user()['id']);flash('success','Clearance period updated.');redirect('admin/clearance_period.php');}catch(Throwable $e){$error=page_error($e,$errors);}}
$period=clearance_period_state($pdo);$cycles=$pdo->query('SELECT * FROM academic_cycles WHERE active=1 ORDER BY label DESC')->fetchAll();$pageTitle='Clearance Period Management';require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading period-heading">
    <div><span class="eyebrow">ADMINISTRATION / CLEARANCE</span><h1>Clearance Period Management</h1><p class="muted">Set the academic cycle and decide when students can start or resubmit clearance.</p></div>
    <a class="btn secondary" href="<?=e(url('admin/dashboard.php'))?>"><?=icon('home')?> Dashboard</a>
</div>
<?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?>
<div class="period-layout">
    <aside class="period-overview" aria-label="Saved clearance period">
        <section class="panel period-status-card <?= $period['is_open']?'is-open':'is-closed' ?>">
            <div class="period-status-top"><span class="period-symbol"><?=icon('calendar-clock')?></span><span class="badge <?= $period['is_open']?'success':'warning' ?>"><?=e($period['effective_status'])?></span></div>
            <span class="eyebrow">CURRENT CLEARANCE PERIOD</span>
            <h2>Clearance is <?= $period['is_open']?'open':'closed' ?></h2>
            <p><?= $period['is_open']?'Students in the selected academic cycle can start or resubmit clearance.':'Students cannot start or resubmit clearance until the period opens.' ?></p>
            <div class="period-cycle"><?=icon('graduation-cap')?><div><span>Academic cycle</span><strong><?=e($period['academic_cycle']?:'Not selected')?></strong></div></div>
            <div class="period-control-label">Control mode <strong><?=e(['MANUAL_OPEN'=>'Manual opening','MANUAL_CLOSED'=>'Manual closure','SCHEDULED'=>'Scheduled dates'][$period['mode']]??$period['mode'])?></strong></div>
        </section>
        <section class="panel period-schedule">
            <h2><?=icon('calendar')?> Saved schedule</h2>
            <p class="muted">Dates and times use East Africa Time (EAT).</p>
            <ol class="period-timeline">
                <li><span>Opening date &amp; time</span><strong><?=e($period['opens_at']?:'Not scheduled')?></strong></li>
                <li><span>Closing date &amp; time</span><strong><?=e($period['closes_at']?:'Not scheduled')?></strong></li>
            </ol>
            <?php if($period['mode']!=='SCHEDULED'): ?><p class="period-note">Manual control is active. Saved dates apply only when scheduled control is selected.</p><?php endif; ?>
            <div class="period-audit"><span>Last updated by</span><strong><?=e($period['updated_by_name']?:'Not yet updated')?></strong><span><?=e($period['updated_at']?:'No update recorded')?> (EAT)</span></div>
            <?php if($period['remarks']!==''): ?><div class="period-saved-remarks"><span>Saved remarks</span><p><?=e($period['remarks'])?></p></div><?php endif; ?>
        </section>
    </aside>
    <section class="panel period-settings">
        <div class="period-settings-heading"><span class="period-symbol"><?=icon('calendar')?></span><div><h2>Manage clearance access</h2><p class="muted">Choose a mode, review the dates and save your changes.</p></div></div>
        <form method="post" onsubmit="return confirmAction('Are you sure you want to change the clearance period?');">
            <?php csrf_field(); ?>
            <fieldset class="period-fieldset">
                <legend><span>01</span> Access &amp; academic cycle</legend>
                <?php form_fields(['mode'=>['label'=>'Control mode','type'=>'select','required'=>true,'options'=>['MANUAL_OPEN'=>'Open clearance manually','MANUAL_CLOSED'=>'Close clearance manually','SCHEDULED'=>'Use scheduled opening and closing dates'],'value'=>$period['mode'],'help'=>'Manual control takes effect when saved. Scheduled control uses the dates below.']],$errors); ?>
                <div class="period-field-grid">
                    <div><?php form_fields(['cycle_id'=>['label'=>'Academic cycle','type'=>'select','options'=>option_rows($cycles,'label'),'value'=>$period['cycle_id']??'']],$errors); ?></div>
                    <div><?php form_fields(['new_cycle'=>['label'=>'New academic cycle (optional)','pattern'=>'20[0-9]{2}/20[0-9]{2}','maxlength'=>20,'help'=>'To add a cycle not listed, enter consecutive years, for example 2026/2027.']],$errors); ?></div>
                </div>
            </fieldset>
            <fieldset class="period-fieldset">
                <legend><span>02</span> Opening &amp; closing dates</legend>
                <p class="muted">For scheduled control, enter both dates with closing after opening. All times are EAT.</p>
                <div class="period-field-grid">
                    <div><?php form_fields(['opens_at'=>['label'=>'Opening date/time','type'=>'datetime-local','value'=>$period['opens_at']?str_replace(' ','T',substr($period['opens_at'],0,16)):'']],$errors); ?></div>
                    <div><?php form_fields(['closes_at'=>['label'=>'Closing date/time','type'=>'datetime-local','value'=>$period['closes_at']?str_replace(' ','T',substr($period['closes_at'],0,16)):'']],$errors); ?></div>
                </div>
            </fieldset>
            <fieldset class="period-fieldset">
                <legend><span>03</span> Reason for this update</legend>
                <?php form_fields(['remarks'=>['label'=>'Remarks / reason','type'=>'textarea','required'=>true,'maxlength'=>1000,'value'=>$period['remarks']]],$errors); ?>
            </fieldset>
            <div class="period-save-bar"><p><?=icon('info')?> Changes take effect after you confirm and save.</p><button class="btn primary" type="submit"><?=icon('check-circle')?> Save clearance period</button></div>
        </form>
    </section>
</div>
<?php require_once __DIR__.'/../includes/footer.php';?>
