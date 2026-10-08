<?php
declare(strict_types=1);

// Copy to mail.local.php, enter sender credentials, then enable sending.
return [
    'enabled' => false,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-sender@gmail.com',
    'password' => '', // Gmail App Password; never the regular account password.
    'from_email' => 'your-sender@gmail.com',
    'from_name' => 'IRDP e-Clearance',
    'timeout' => 15,
];
