<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $name = text_input($_POST, 'name', 180);
        $description = text_input($_POST, 'description', 255, false);

        if ($name === '') {
            throw new RuntimeException('Office name is required.');
        }

        unique_reference_value($pdo, 'offices', 'name', $name, 'name');
        $pdo->prepare(
            'INSERT INTO offices (name, description, active) VALUES (?, ?, 1)'
        )->execute([$name, $description]);
        audit($pdo, 'OFFICE_CREATED', (int)current_user()['id'], null, $name);

        flash('success', 'Office added successfully.');
        redirect('admin/offices.php');
    } catch (RuntimeException $e) {
        $error = page_error($e, $errors);
    } catch (Throwable $e) {
        $error = 'Office could not be added. It may already exist.';
    }
}

$rows = $pdo->query(
    'SELECT o.*, COUNT(DISTINCT uo.user_id) AS officer_count,
            COUNT(DISTINCT ws.id) AS workflow_count
     FROM offices o
     LEFT JOIN user_offices uo ON uo.office_id = o.id
     LEFT JOIN workflow_steps ws ON ws.office_id = o.id
     GROUP BY o.id
     ORDER BY o.id'
)->fetchAll();

$pageTitle = 'Manage Offices';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">ADMIN</span>
        <h1>Manage Offices</h1>
        <p class="muted">Configure offices used by the clearance workflow.</p>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2>Add Office</h2>
        <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="name">Office Name</label>
            <input id="name" name="name" maxlength="180" value="<?= old_value('name') ?>" required>
            <?php form_error('name', $errors); ?>
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="255"><?= old_value('description') ?></textarea>
            <?php form_error('description', $errors); ?>
            <button class="btn primary" type="submit">Add Office</button>
        </form>
    </section>

    <section class="panel">
        <h2>Configured Offices</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Office</th><th>Officers</th><th>Workflow Steps</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong><br><small class="muted"><?= e($row['description']) ?></small></td>
                        <td><?= (int) $row['officer_count'] ?></td>
                        <td><?= (int) $row['workflow_count'] ?></td>
                        <td><?= $row['active'] ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
