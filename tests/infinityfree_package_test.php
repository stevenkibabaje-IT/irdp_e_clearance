<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$package = realpath($argv[1] ?? '');
if (!$package || !is_file($package . '/database.sql')) { throw new RuntimeException('Pass the deployment directory.'); }
$db = db_connect(false);
$testName = 'irdp_hosting_check_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$db->exec('CREATE DATABASE `' . $testName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$db->exec('USE `' . $testName . '`');
$db->exec(file_get_contents($package . '/database.sql'));
function verify(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
verify((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() === 28, 'Expected 28 tables.');
verify((int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn() === 0, 'Student data must not be exported.');
verify((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Expected one fresh admin.');
$admin = $db->query("SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id=u.role_id")->fetch();
preg_match('/^Password: (.+)$/m', file_get_contents($package . '/ADMIN-LOGIN-PRIVATE.txt'), $match);
verify($admin['role'] === 'ADMIN' && (int)$admin['force_password_change'] === 1 && password_verify(trim($match[1]), $admin['password_hash']), 'Administrator login check failed.');
verify((int)$db->query('SELECT COUNT(*) FROM workflow_steps')->fetchColumn() === 11, 'Expected 11 office stages.');
verify($db->query('SELECT mode FROM clearance_period WHERE id=1')->fetchColumn() === 'MANUAL_CLOSED', 'Clearance must start closed.');
$zip = new ZipArchive();
verify($zip->open($package . '/irdp-infinityfree-upload.zip') === true, 'Cannot open upload ZIP.');
foreach (['README.md','database.sql','config/mail.local.php','config/demo_students.php','ADMIN-LOGIN-PRIVATE.txt','setup.php','diagnostics.php'] as $excluded) {
    verify($zip->locateName($excluded) === false, 'Private/unnecessary file included: ' . $excluded);
}
verify($zip->locateName('.htaccess') !== false && $zip->locateName('storage/.htaccess') !== false, 'Access controls are missing.');
$zip->close();
echo "PASS: SQL restored to isolated database $testName; 28 tables, 11 stages, fresh forced-change admin, zero students, ZIP exclusions verified.\n";
// Keep the isolated database for review; never change or drop the live database.
