<?php
declare(strict_types=1);

// Retired feature URL: return authenticated staff to their dashboard.
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN', 'SUPERVISOR');
redirect(role_dashboard(current_user()['role']));
