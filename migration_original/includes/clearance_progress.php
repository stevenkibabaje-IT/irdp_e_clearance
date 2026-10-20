<?php
declare(strict_types=1);

/** Shared, accessible progress summary for independently reviewed offices. */
function clearance_progress(array $stages): void
{
    $total = max(11, count($stages));
    $approved = count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'APPROVED'));
    $actionRequired = count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'REJECTED'));
    $pending = $total - $approved - $actionRequired;
    $progress = (int) round(($approved / $total) * 100);
    ?>
    <div class="clearance-progress">
        <p class="muted clearance-progress-note">Each office reviews independently.</p>
        <div class="clearance-progress-layout">
            <div class="progress-ring" role="progressbar" aria-label="Clearance progress"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>"
                 aria-valuetext="<?= $approved ?> of <?= $total ?> offices approved (<?= $progress ?>%)">
                <svg class="progress-ring-chart" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                    <circle class="progress-ring-track" cx="60" cy="60" r="52" />
                    <circle class="progress-ring-value" cx="60" cy="60" r="52" pathLength="100"
                            stroke-dasharray="<?= $progress ?> 100" transform="rotate(-90 60 60)" />
                </svg>
                <div class="progress-ring-label" aria-hidden="true">
                    <strong><?= $progress ?><span>%</span></strong>
                    <span>Complete</span>
                </div>
            </div>
            <div class="clearance-progress-summary">
                <p class="clearance-progress-total"><strong><?= $approved ?> of <?= $total ?></strong><span>offices approved</span></p>
                <div class="clearance-progress-metrics">
                    <div class="progress-metric approved">
                        <span class="progress-metric-icon"><?= icon('check-circle') ?></span>
                        <div><strong><?= $approved ?></strong><span>Approved</span></div>
                    </div>
                    <div class="progress-metric pending">
                        <span class="progress-metric-icon"><?= icon('clock') ?></span>
                        <div><strong><?= $pending ?></strong><span>Pending</span></div>
                    </div>
                    <div class="progress-metric action-required">
                        <span class="progress-metric-icon"><?= icon('alert-circle') ?></span>
                        <div><strong><?= $actionRequired ?></strong><span>Action required</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
