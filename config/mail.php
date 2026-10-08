<?php
declare(strict_types=1);

/** SMTP credentials stay in the ignored, web-protected mail.local.php file. */
function notification_mail_config(): array
{
    $config = [
        'enabled' => false, 'host' => '', 'port' => 587, 'encryption' => 'tls',
        'username' => '', 'password' => '', 'from_email' => '',
        'from_name' => 'IRDP e-Clearance', 'timeout' => 15,
    ];
    $path = __DIR__ . '/mail.local.php';
    if (is_file($path)) {
        $local = require $path;
        if (!is_array($local)) { throw new RuntimeException('SMTP configuration must return an array.'); }
        $config = array_replace($config, $local);
    }
    return $config;
}

function notification_mail_problem(array $config): ?string
{
    if (empty($config['enabled'])) { return 'Email sending is switched off.'; }
    if (!is_string($config['host']) || !preg_match('/^[a-zA-Z0-9.-]+$/D', $config['host'])) {
        return 'Set a valid SMTP host.';
    }
    if (!in_array($config['encryption'], ['tls', 'ssl', 'none'], true)
        || ($config['encryption'] === 'none' && !in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true))) {
        return 'Use TLS or SSL for the SMTP connection.';
    }
    if (!is_numeric($config['port']) || (int)$config['port'] < 1 || (int)$config['port'] > 65535) {
        return 'Set a valid SMTP port.';
    }
    if (!is_string($config['from_email']) || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        return 'Set a valid sender email address.';
    }
    if (!is_string($config['from_name']) || preg_match('/[\r\n]/', $config['from_name'])) {
        return 'Set a valid sender name.';
    }
    if (!is_string($config['username']) || !is_string($config['password'])
        || ($config['username'] !== '' && $config['password'] === '')) {
        return 'Set the SMTP password in config/mail.local.php.';
    }
    return null;
}
