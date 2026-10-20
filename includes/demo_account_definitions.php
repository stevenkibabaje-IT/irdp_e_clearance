<?php
declare(strict_types=1);

/** Read account tables from the single local README without a second password list. */
function documented_demo_accounts(): array
{
    $accounts = [];
    $role = null;
    foreach (file(__DIR__ . '/../README.md', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^### (ADMIN|OFFICER|SUPERVISOR|STUDENT)\b/',$line,$match)) { $role=$match[1];continue; }
        if ($role!==null && str_starts_with($line,'## ')) { break; }
        if (!str_starts_with($line, '|') || $role === null) { continue; }
        $cells = array_map('trim', explode('|', trim($line, '|')));
        $username = $cells[1] ?? '';
        if (count($cells)!==6 || ($cells[5]??'')!=='ACTIVE' || $username==='Username') { continue; }
        if (isset($accounts[$username])) { throw new RuntimeException('Duplicate documented account: ' . $username); }
        $accounts[$username] = [
            'username' => $username, 'role' => $role,
            'password' => $cells[2],
            'full_name' => $cells[0], 'programme' => $cells[3] ?? null,
            'department' => $cells[4],
        ];
    }
    if (!$accounts) { throw new RuntimeException('No account tables found in README.md.'); }
    return $accounts;
}
