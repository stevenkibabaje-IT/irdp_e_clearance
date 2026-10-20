<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('ADMIN');
require_once __DIR__.'/../includes/reporting.php';
$error=''; $report=null;
try { $report=clearance_report_data($pdo,$_GET); }
catch (InvalidArgumentException $e) { http_response_code(400); $error=$e->getMessage(); }
$f=$report['filters']??array_fill_keys(['report','from','to','year','programme','department','office','status'],'');
$options=[
 'year'=>['Academic year',$pdo->query('SELECT label AS value,label AS label FROM academic_cycles UNION SELECT academic_year,academic_year FROM clearance_requests ORDER BY label DESC')->fetchAll()],
 'programme'=>['Programme',$pdo->query('SELECT DISTINCT programme AS value,programme AS label FROM students ORDER BY programme')->fetchAll()],
 'department'=>['Student department',$pdo->query('SELECT id AS value,name AS label FROM departments ORDER BY name')->fetchAll()],
 'office'=>['Clearance office',$pdo->query('SELECT id AS value,name AS label FROM offices ORDER BY name')->fetchAll()],
 'status'=>['Request status',array_map(static fn($s)=>['value'=>$s,'label'=>humanize_status($s)],['NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED'])],
];
$pageTitle='Clearance Reports'; require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">REPORTING</span><h1>Clearance Reports</h1><p class="muted">Clearance, payments, office performance and recorded system activity.</p></div></div>
<?php if($error!==''): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><h2>Choose report and filters</h2><form method="get">
<label for="report">Report type</label><select id="report" name="report"><?php foreach(clearance_report_catalog() as $key=>$title): ?><option value="<?= e($key) ?>" <?= $f['report']===$key?'selected':'' ?>><?= e($title) ?></option><?php endforeach; ?></select>
<div class="two-col"><?php foreach(['from'=>'From','to'=>'To'] as $key=>$label): ?><div><label for="<?= $key ?>"><?= $label ?> (optional)</label><input type="date" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($f[$key]) ?>"></div><?php endforeach; ?></div>
<p class="muted">Leave dates blank for all dates. Access Period reports support dates only; clear other filters for that report.</p>
<div class="two-col"><?php foreach($options as $key=>[$label,$values]): ?><div><label for="<?= $key ?>"><?= e($label) ?></label><select id="<?= $key ?>" name="<?= $key ?>"><option value="">All</option><?php foreach($values as $v): ?><option value="<?= e((string)$v['value']) ?>" <?= $f[$key]===(string)$v['value']?'selected':'' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div>
<div class="form-actions"><button class="btn primary" type="submit">View report</button><a class="btn secondary" href="<?= e(url('admin/reports.php')) ?>">Reset filters</a></div>
</form></section>
<?php if($report): $export='reports/generate.php?'.http_build_query($f); $total=count($report['rows']); $pages=max(1,(int)ceil($total/50)); $page=min($pages,max(1,(int)($_GET['page']??1))); ?>
<section class="panel"><div class="panel-head"><div><h2><?= e($report['title']) ?></h2><p><?= $total ?> result rows · Generated <?= e(date('Y-m-d H:i')) ?> EAT</p></div><div class="form-actions"><a class="btn secondary" href="<?= e(url($export.'&format=csv')) ?>">Download CSV</a><a class="btn primary" href="<?= e(url($export.'&format=pdf')) ?>">Download PDF</a></div></div>
<p class="muted"><?= e($report['notes']) ?></p><div class="table-wrap"><table><thead><tr><?php foreach($report['columns'] as $column): ?><th scope="col"><?= e(str_replace('_',' ',$column)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach(array_slice($report['rows'],($page-1)*50,50) as $row): ?><tr><?php foreach($report['columns'] as $column): ?><td><?= nl2br(e((string)($row[$column]??'—'))) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if(!$total): ?><tr><td colspan="<?= count($report['columns']) ?>">No records match these filters.</td></tr><?php endif; ?></tbody></table></div>
<nav class="form-actions" aria-label="Report pages"><?php if($page>1): ?><a class="btn secondary" href="<?= e(url('admin/reports.php?'.http_build_query($f+['page'=>$page-1]))) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if($page<$pages): ?><a class="btn secondary" href="<?= e(url('admin/reports.php?'.http_build_query($f+['page'=>$page+1]))) ?>">Next</a><?php endif; ?></nav></section><?php endif; ?>
<section class="panel"><h2>Period summaries</h2><div class="form-actions"><a class="btn secondary" href="<?= e(url('reports/generate.php?type=weekly')) ?>">Weekly PDF</a><a class="btn secondary" href="<?= e(url('reports/generate.php?type=monthly')) ?>">Monthly PDF</a></div></section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
