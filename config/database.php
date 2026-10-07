<?php
declare(strict_types=1);

/** Environment settings override the local XAMPP database connection defaults. */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'irdp_e_clearance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', DB_NAME) || !ctype_digit((string)DB_PORT) || (int)DB_PORT<1 || (int)DB_PORT>65535) {
    throw new RuntimeException('Invalid database name or port configuration.');
}

function build_database_dsn(string $host, bool $withDatabase = true): string
{
    $database = $withDatabase ? ';dbname=' . DB_NAME : '';

    if ($host === 'localhost') {
        return 'mysql:host=localhost;port=' . DB_PORT . $database . ';charset=utf8mb4';
    }

    if ($host === 'socket') {
        return 'mysql:unix_socket=C:/xampp 1/mysql/mysql.sock' . $database . ';charset=utf8mb4';
    }

    return 'mysql:host=' . $host . ';port=' . DB_PORT . $database . ';charset=utf8mb4';
}

function db_connect(bool $withDatabase = true): PDO
{
    $candidates = [];
    $preferredHost = getenv('DB_HOST') ?: DB_HOST;

    if ($preferredHost !== '') {
        $candidates[] = $preferredHost;
    }

    $candidates = array_values(array_unique(array_merge($candidates, ['127.0.0.1', 'localhost', 'socket'])));

    $lastError = null;

    foreach ($candidates as $host) {
        try {
            $dsn = build_database_dsn($host, $withDatabase);
            $connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $connection->exec("SET time_zone = '+03:00'");
            return $connection;
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($lastError !== null) {
        throw $lastError;
    }

    throw new RuntimeException('Unable to connect to the database.');
}
