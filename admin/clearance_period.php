<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_role('ADMIN');$error='';$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){try{verify_csrf();update_clearance_period($pdo,$_POST,(int)current_user()['id']);flash('success','Clearance period updated.');redirect('admin/clearance_period.php');}catch(Throwable $e){$error=page_error($e,$errors);}}
$period=clearance_period_state($pdo);$cycles=$pdo->query('SELECT * FROM academic_cycles WHERE active=1 ORDER BY label DESC')->fetchAll();$pageTitle='Clearance Period Management';require_once __DIR__.'/../includes/header.php';
?><div class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Clearance Period Management</h1><p class="muted">Control when students may start or resubmit clearance.</p></div><span class="<?=e($period['is_open']?'badge success':'badge muted')?>"><?=e($period['effective_status'])?></span></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<section class="panel"><h2>Current clearance period</h2><div class="detail-grid"><div><span>Status</span><strong><?=e($period['effective_status'])?></strong></div><div><span>Academic Cycle</span><strong><?=e($period['academic_cycle']?:'Not selected')?></strong></div><div><span>Opening</span><strong><?=e($period['opens_at']?:'Manual control')?></strong></div><div><span>Closing</span><strong><?=e($period['closes_at']?:'Manual control')?></strong></div><div><span>Last updated by</span><strong><?=e($period['updated_by_name']?:'Not yet updated')?></strong></div><div><span>Last updated</span><strong><?=e($period['updated_at'])?></strong></div></div><p><?=e($period['remarks'])?></p></section>
<section class="panel"><h2>Open, close or schedule</h2>
<form method="post" onsubmit="return confirmAction('Are you sure you want to change the clearance period?');">
<?php csrf_field(); form_fields([
    'mode'=>['label'=>'Control','type'=>'select','required'=>true,'options'=>['MANUAL_OPEN'=>'OPEN CLEARANCE','MANUAL_CLOSED'=>'CLOSE CLEARANCE','SCHEDULED'=>'Save scheduled dates'],'value'=>$period['mode']],
    'cycle_id'=>['label'=>'Academic cycle','type'=>'select','options'=>option_rows($cycles,'label'),'value'=>$period['cycle_id'] ?? ''],
    'new_cycle'=>['label'=>'New academic cycle (optional)','pattern'=>'20[0-9]{2}/20[0-9]{2}','maxlength'=>20,'help'=>'To add a cycle not listed, enter consecutive years, for example 2026/2027.'],
    'opens_at'=>['label'=>'Opening date/time','type'=>'datetime-local','value'=>$period['opens_at']?str_replace(' ','T',substr($period['opens_at'],0,16)):''],
    'closes_at'=>['label'=>'Closing date/time','type'=>'datetime-local','value'=>$period['closes_at']?str_replace(' ','T',substr($period['closes_at'],0,16)):''],
    'remarks'=>['label'=>'Remarks / reason','type'=>'textarea','required'=>true,'maxlength'=>1000,'value'=>$period['remarks']]
],$errors); ?>
<button class="btn primary" type="submit">Save clearance period</button></form></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
