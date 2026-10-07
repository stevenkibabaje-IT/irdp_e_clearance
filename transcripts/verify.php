<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
$s = $pdo->prepare('SELECT * FROM transcripts WHERE verification_token=? LIMIT 1');
$s->execute([preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '']);
$doc = $s->fetch();
$valid = $doc && $doc['status'] === 'VALID'
    && hash_equals($doc['snapshot_sha256'], hash('sha256', (string)$doc['snapshot_json']));
$snapshot = $valid ? json_decode($doc['snapshot_json'], true) : null;
$valid = $valid && is_array($snapshot);
$isClearance = $valid && $doc['result_source'] === 'CLEARANCE'
    && ($snapshot['document_type'] ?? '') === 'CLEARANCE_TRANSCRIPT';
$pageTitle = 'Clearance Transcript Verification';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel" style="max-width:720px;margin:auto">
    <h1><?= $valid ? 'DOCUMENT VERIFIED' : 'DOCUMENT INVALID' ?></h1>
    <?php if ($doc && $doc['status'] === 'REVOKED'): ?>
        <div class="alert danger">DOCUMENT REVOKED</div>
    <?php elseif ($valid): ?>
        <div class="alert success">VALID</div>
        <?php if (!$isClearance): ?>
            <div class="alert info">Previous transcript format. Download the current clearance transcript from your dashboard.</div>
        <?php endif; ?>
        <div class="detail-grid">
            <div><span>Document type</span><strong><?= $isClearance ? 'Clearance transcript' : 'Previous transcript format' ?></strong></div>
            <div><span>Student</span><strong><?= e($snapshot['student']['name'] ?? '-') ?></strong></div>
            <div><span>Registration</span><strong><?= e($snapshot['student']['registration_number'] ?? '-') ?></strong></div>
            <div><span>Programme</span><strong><?= e($snapshot['student']['programme'] ?? '-') ?></strong></div>
            <div><span>Academic cycle</span><strong><?= e($snapshot['cycle'] ?? '-') ?></strong></div>
            <?php if ($isClearance): ?>
                <div><span>Clearance status</span><strong><?= e($snapshot['clearance']['status']) ?></strong></div>
                <div><span>Approved offices</span><strong><?= count($snapshot['approvals']) ?></strong></div>
                <div><span>Completed</span><strong><?= e($snapshot['clearance']['completed_at']) ?></strong></div>
            <?php endif; ?>
            <div><span>Document number</span><strong><?= e($doc['document_number']) ?></strong></div>
            <div><span>Generated</span><strong><?= e($doc['generated_at']) ?></strong></div>
        </div>
    <?php else: ?>
        <p>This verification token is invalid or the document snapshot cannot be verified.</p>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
