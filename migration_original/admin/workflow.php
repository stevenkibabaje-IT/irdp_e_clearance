<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $stepNumber = positive_id($_POST['step_number'] ?? null, 'step_number');
        $title = text_input($_POST, 'title', 180);
        $officeId = positive_id($_POST['office_id'] ?? null, 'office_id');

        if ($stepNumber < 1 || $stepNumber > 11 || $title === '' || $officeId < 1) {
            throw new ValidationException(['step_number'=>'Choose a stage number from 1 to 11.']);
        }

        $officeCheck = $pdo->prepare('SELECT id FROM offices WHERE id = ? AND active = 1');
        $officeCheck->execute([$officeId]);
        if (!$officeCheck->fetchColumn()) {
            throw new ValidationException(['office_id'=>'Choose an active office.']);
        }

        $pdo->prepare(
            'INSERT INTO workflow_steps (step_number, title, office_id, active)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE title = VALUES(title), office_id = VALUES(office_id), active = 1'
        )->execute([$stepNumber, $title, $officeId]);
        audit($pdo, 'WORKFLOW_STEP_UPDATED', (int)current_user()['id'], null, 'Stage '.$stepNumber.' office '.$officeId);

        flash('success', 'Workflow step saved.');
        redirect('admin/workflow.php');
    } catch (RuntimeException $e) {
        $error = page_error($e, $errors);
    } catch (Throwable $e) {
        $error = 'Workflow step could not be added. The step number may already exist.';
    }
}

$steps = $pdo->query(
    'SELECT ws.*, o.name AS office
     FROM workflow_steps ws
     INNER JOIN offices o ON o.id = ws.office_id
     ORDER BY ws.step_number'
)->fetchAll();
$offices = $pdo->query('SELECT id, name FROM offices WHERE active = 1 ORDER BY name')->fetchAll();

$pageTitle = 'Workflow';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">ADMIN</span>
        <h1>Workflow</h1>
        <p class="muted">The seeded 11 stages match the physical clearance form order.</p>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2>Configure Workflow Step</h2>
        <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="step_number">Step Number</label>
            <input id="step_number" name="step_number" type="number" min="1" max="11" value="<?= old_value('step_number') ?>" required>
            <?php form_error('step_number', $errors); ?>
            <label for="title">Stage Title</label>
            <input id="title" name="title" maxlength="180" value="<?= old_value('title') ?>" required>
            <?php form_error('title', $errors); ?>
            <label for="office_id">Office</label>
            <select id="office_id" name="office_id" required>
                <?php foreach ($offices as $office): ?>
                    <option value="<?= (int) $office['id'] ?>" <?= old_value('office_id')===(string)$office['id']?'selected':'' ?>><?= e($office['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php form_error('office_id', $errors); ?>
            <button class="btn primary" type="submit">Save Step</button>
        </form>
    </section>

    <section class="panel">
        <h2>Current Workflow</h2>
        <div class="stage-list">
            <?php foreach ($steps as $step): ?>
                <article class="stage-card">
                    <div class="stage-number"><?= (int) $step['step_number'] ?></div>
                    <div class="stage-main">
                        <h3><?= e($step['title']) ?></h3>
                        <p><?= e($step['office']) ?></p>
                    </div>
                    <span class="badge success">ACTIVE</span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
