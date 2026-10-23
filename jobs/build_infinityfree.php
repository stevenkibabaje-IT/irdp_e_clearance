<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
if (!class_exists(ZipArchive::class)) { throw new RuntimeException('PHP ZIP extension is required.'); }
$root = dirname(__DIR__);
$output = $root . '/storage/private/deployment-' . date('Ymd-His');
$web = $output . '/htdocs';
mkdir($web, 0700, true);
$allowed = ['admin','assets','auth','certificates','config','files','includes','officer','reports','student','supervisor','transcripts'];
foreach ($allowed as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_ends_with($relative, '.ps1') || str_ends_with($relative, '.vbs') ||
            in_array($relative, ['config/mail.local.php','config/mail.example.php','config/demo_students.php','includes/demo_account_definitions.php'], true)) { continue; }
        $target = $web . '/' . $relative;
        if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); }
        copy($file->getPathname(), $target);
    }
}
copy($root . '/index.php', $web . '/index.php');
$htaccess = file_get_contents($root . '/.htaccess');
$htaccess = preg_replace('/<IfModule php_module>.*?<\/IfModule>/s', '', $htaccess);
file_put_contents($web . '/.htaccess', $htaccess);
mkdir($web . '/storage/private', 0700, true);
copy($root . '/storage/.htaccess', $web . '/storage/.htaccess');
$application = file_get_contents($web . '/config/application.php');
$application = str_replace("'http://localhost/Irdp_e_clearance'", "'https://irdp-eclearance.infinityfreeapp.com'", $application);
file_put_contents($web . '/config/application.php', $application);
$connection = file_get_contents($web . '/config/database.php');
$connection = str_replace("getenv('DB_HOST') ?: '127.0.0.1'", "getenv('DB_HOST') ?: 'REPLACE_MYSQL_HOSTNAME'", $connection);
$connection = str_replace("getenv('DB_NAME') ?: 'irdp_e_clearance'", "getenv('DB_NAME') ?: 'REPLACE_DATABASE_NAME'", $connection);
$connection = str_replace("getenv('DB_USER') ?: 'root'", "getenv('DB_USER') ?: 'REPLACE_DATABASE_USERNAME'", $connection);
$connection = str_replace("getenv('DB_PASS') : ''", "getenv('DB_PASS') : 'REPLACE_DATABASE_PASSWORD'", $connection);
file_put_contents($web . '/config/database.php', $connection);
// Hosted deployments must import the prepared database, never auto-seed known demo credentials.
$installer = file_get_contents($web . '/includes/installer.php');
$start = strpos($installer, 'function application_database(): PDO');
$end = strpos($installer, '/**', $start);
$safeStartup = <<<'PHP'
function application_database(): PDO
{
    $db = db_connect();
    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    if (!database_schema_ready($db)) {
        throw new RuntimeException('Import database.sql through hosting phpMyAdmin before opening the site.');
    }
    return $db;
}

PHP;
$installer = substr($installer, 0, $start) . $safeStartup;
file_put_contents($web . '/includes/installer.php', $installer);
$schema = $output . '/database.sql';
$command = ['C:/xampp/mysql/bin/mysqldump.exe', '--host=' . DB_HOST, '--port=' . DB_PORT,
    '--user=' . DB_USER, '--no-data', '--skip-lock-tables', '--skip-add-drop-table',
    '--skip-comments', '--result-file=' . $schema, DB_NAME];
$environment = getenv();
$environment['MYSQL_PWD'] = DB_PASS;
$pipes = [];
$process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $environment);
if (!is_resource($process)) { throw new RuntimeException('Cannot launch database export.'); }
fclose($pipes[0]);
stream_get_contents($pipes[1]); fclose($pipes[1]);
$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($process) !== 0) { throw new RuntimeException('Schema export failed: ' . $error); }
$db = db_connect();
$sql = "\nSET FOREIGN_KEY_CHECKS=0;\n";
foreach (['roles','departments','programmes','offices','workflow_steps','academic_cycles','schema_migrations'] as $table) {
    foreach ($db->query('SELECT * FROM `' . $table . '`') as $row) {
        $columns = implode(',', array_map(fn($key)=>'`' . $key . '`', array_keys($row)));
        $values = implode(',', array_map(fn($value)=>$value === null ? 'NULL' : $db->quote((string)$value), array_values($row)));
        $sql .= 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES (' . $values . ");\n";
    }
}
$password = 'Irdp!' . bin2hex(random_bytes(12));
$hash = $db->quote(password_hash($password, PASSWORD_DEFAULT));
$sql .= "INSERT INTO users (username,password_hash,full_name,role_id,active,force_password_change) SELECT 'hosting_admin', $hash, 'Hosting Administrator', id, 1, 1 FROM roles WHERE name='ADMIN';\n";
$sql .= "INSERT INTO clearance_period (id,mode,remarks) VALUES (1,'MANUAL_CLOSED','Configure the academic cycle and officers before opening clearance.');\nSET FOREIGN_KEY_CHECKS=1;\n";
file_put_contents($schema, $sql, FILE_APPEND);
file_put_contents($output . '/ADMIN-LOGIN-PRIVATE.txt', "DO NOT UPLOAD THIS FILE.\nUsername: hosting_admin\nPassword: $password\nChange this temporary password at first login.\n");
$guide = <<<'TEXT'
INFINITYFREE DEMO DEPLOYMENT
Website: https://irdp-eclearance.infinityfreeapp.com

1. Create a database using InfinityFree Control Panel > MySQL Databases.
2. In htdocs/config/database.php replace the four REPLACE_... values with the
   hostname, full database name, username and password from your hosting account.
   Do this privately; never send the password in chat. Do not use localhost.
3. Upload the CONTENTS of htdocs/ into the domain's htdocs directory using FTP
   or File Manager. Extract the local ZIP on your PC first. Include .htaccess files.
   Do not upload database.sql, this guide, or ADMIN-LOGIN-PRIVATE.txt.
4. Open hosting phpMyAdmin, select the NEW EMPTY database and import database.sql.
   This file creates tables; it must not be imported over an existing database.
5. Enable/install SSL in InfinityFree and verify the HTTPS URL before signing in.
6. Sign in using the separate ADMIN-LOGIN-PRIVATE.txt file; change the password.
7. Add officers/students, assign offices and departments, configure academic cycle,
   clearance dates and fee rates. This package has no existing student data/files.
8. Check homepage/login, all roles, receipts/evidence, reports, certificate PDF and
   QR verification, and denied access to /config/database.php and /storage/.

IMPORTANT LIMITATIONS
- Prepared for a demonstration, not a verified production deployment.
- Local XAMPP data/settings were not changed. Existing real accounts, uploads,
  local SMTP secrets, README, tests, prototypes, setup/diagnostics and Windows
  scheduled tasks are excluded. A new admin password is generated per build.
- SMTP/email delivery is disabled; Windows workers/backups do not run here.
- Back up the hosted database using phpMyAdmin Export and download private uploads
  using FTP. The existing PC backup does NOT back up the hosted database.
- This package uses the current application's manual payment workflow; it does
  not provide bank/GePG payment integration.
- Check PHP 8.1+ with PDO MySQL, mbstring, fileinfo, GD, ZIP and upload support.
- Never publish real student information until access controls and deployment
  behavior have been tested on the hosting account.
TEXT;
file_put_contents($output . '/DEPLOYMENT.txt', $guide);
$zip = new ZipArchive();
$zipPath = $output . '/irdp-infinityfree-upload.zip';
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) { throw new RuntimeException('Cannot create ZIP.'); }
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS)) as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($web) + 1));
    $limit = str_ends_with($relative, '.php') || str_ends_with($relative, '.html') ? 1000000 : 10000000;
    if ($file->getSize() > $limit) { throw new RuntimeException('Hosting size limit exceeded: ' . $relative); }
    $zip->addFile($file->getPathname(), $relative);
}
$zip->addEmptyDir('storage/private');
$zip->close();
echo "Prepared: $output\n";
