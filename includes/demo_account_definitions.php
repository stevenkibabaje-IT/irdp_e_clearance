<?php
declare(strict_types=1);

/** Read the published demo credentials without maintaining a second password list. */
function documented_demo_accounts(): array
{
    $accounts = [];
    $role = null;
    foreach (file(__DIR__ . '/../README_DEMO_ACCOUNTS.md', FILE_IGNORE_NEW_LINES) as $line) {
        if (str_starts_with($line, '## 2.')) { $role = 'STUDENT'; }
        if (str_starts_with($line, '## 3.')) { $role = 'ADMIN'; }
        if (str_starts_with($line, '## 4.')) { $role = 'OFFICER'; }
        if (str_starts_with($line, '## 5.')) { break; }
        if (!str_starts_with($line, '|') || $role === null) { continue; }
        $cells = array_map('trim', explode('|', trim($line, '|')));
        $username = $cells[$role === 'ADMIN' ? 0 : 1] ?? '';
        if (!preg_match('~^(?:IRDP/[A-Z]+/MA[0-9]{2}/[0-9]{4}|admin|[A-Z]+001)$~D', $username)) { continue; }
        if (isset($accounts[$username])) { throw new RuntimeException('Duplicate documented account: ' . $username); }
        $accounts[$username] = [
            'username' => $username, 'role' => $role,
            'password' => $cells[$role === 'ADMIN' ? 1 : 2],
            'full_name' => $cells[0], 'programme' => $cells[3] ?? null,
            'email' => $role === 'STUDENT' ? ($cells[4] ?? '') : '',
            'academic_year' => str_contains($username, '/MA26/') ? '2026/2027' : '2025/2026',
        ];
    }
    if (count($accounts) !== 33) { throw new RuntimeException('Expected 33 documented demo accounts.'); }
    return $accounts;
}
