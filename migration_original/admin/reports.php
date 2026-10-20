<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'PDF Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">REPORTING</span>
        <h1>PDF Reports</h1>
        <p class="muted">Generate weekly, monthly or custom date-range clearance reports.</p>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2>Generate Report</h2>
        <form method="get" action="<?= e(url('reports/generate.php')) ?>" target="_blank">
            <label for="type">Report Type</label>
            <select id="type" name="type">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="custom">Custom Date Range</option>
            </select>

            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= date('Y-m-d', strtotime('-6 days')) ?>">

            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= date('Y-m-d') ?>">

            <button class="btn primary full" type="submit"><?= icon('download') ?> Generate PDF Report</button>
        </form>
    </section>

    <section class="panel">
        <h2>Report Contents</h2>
        <ul class="clean-list">
            <li>IRDP Student Clearance Report title</li>
            <li>Reporting period and generation date</li>
            <li>Total requests</li>
            <li>Completed, in-progress, student-action-required and not-started counts</li>
            <li>Cancelled request count when applicable</li>
            <li>Approved and rejected actions by office</li>
            <li>Student clearance list and current stage</li>
            <li>Certificate issuance count</li>
        </ul>
    </section>
</div>

<div class="panel">
    <h2>Available Shortcuts</h2>
    <div class="quick-grid">
        <a class="quick-card" target="_blank" href="<?= e(url('reports/generate.php?type=weekly')) ?>"><?= icon('calendar-clock') ?><strong>Weekly Report</strong></a>
        <a class="quick-card" target="_blank" href="<?= e(url('reports/generate.php?type=monthly')) ?>"><?= icon('calendar') ?><strong>Monthly Report</strong></a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
