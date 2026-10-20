<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $code = text_input($_POST, 'code', 30);
        $name = text_input($_POST, 'name', 180);
        if (!preg_match('/^[A-Z][A-Z0-9_-]{1,29}$/D', $code)) { throw new ValidationException(['code'=>'Use 2–30 uppercase letters, digits, underscores or hyphens.']); }

        if ($code === '' || $name === '') {
            throw new RuntimeException('Department code and name are required.');
        }

        unique_reference_value($pdo, 'departments', 'code', $code, 'code');
        unique_reference_value($pdo, 'departments', 'name', $name, 'name');
        $pdo->prepare('INSERT INTO departments (code, name, active) VALUES (?, ?, 1)')->execute([$code, $name]);
        audit($pdo, 'DEPARTMENT_CREATED', (int)current_user()['id'], null, $code);
        flash('success', 'Department added successfully.');
        redirect('admin/departments.php');
    } catch (RuntimeException $e) {
        $error = page_error($e, $errors);
    } catch (Throwable $e) {
        $error = 'Department could not be added. The code or name may already exist.';
    }
}

$rows = $pdo->query(
    'SELECT d.*, COUNT(s.id) AS student_count
     FROM departments d
     LEFT JOIN students s ON s.department_id = d.id
     GROUP BY d.id
     ORDER BY d.name'
)->fetchAll();

$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">ADMIN</span>
        <h1>Departments</h1>
        <p class="muted">Use the official department names provided by IRDP.</p>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2>Add Department</h2>
        <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="code">Department Code</label>
            <input id="code" name="code" maxlength="30" pattern="[A-Z][A-Z0-9_-]{1,29}" value="<?= old_value('code') ?>" required>
            <?php form_error('code', $errors); ?>
            <label for="name">Department Name</label>
            <input id="name" name="name" maxlength="180" value="<?= old_value('name') ?>" required>
            <?php form_error('name', $errors); ?>
            <button class="btn primary" type="submit">Add Department</button>
        </form>
    </section>

    <section class="panel">
        <h2>Departments</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Students</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['code']) ?></td>
                        <td><?= e($row['name']) ?></td>
                        <td><?= (int) $row['student_count'] ?></td>
                        <td><?= $row['active'] ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
