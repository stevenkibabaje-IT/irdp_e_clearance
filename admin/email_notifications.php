<?php
declare(strict_types=1);

// Retired admin controls: email delivery is handled automatically by the worker.
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');
redirect('admin/dashboard.php');
