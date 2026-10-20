<?php
declare(strict_types=1);

// Academic Results has been removed; old links return to the dashboard.
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');
redirect(role_dashboard(current_user()['role']));
