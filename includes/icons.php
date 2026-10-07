<?php
declare(strict_types=1);

/** Local outline SVG icons. Labels on the surrounding controls provide accessible names. */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/><circle cx="9" cy="7" r="4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/>',
        'graduation-cap' => '<path d="m2 8 10-5 10 5-10 5Zm4 2v6c4 3 8 3 12 0v-6M22 8v7"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a20 20 0 0 0 18 0M12 11v4"/>',
        'key' => '<circle cx="8" cy="8" r="5"/><path d="m11.5 11.5 9 9H23v-3l-3-1v-3l-3-1"/>',
        'book-open' => '<path d="M12 5v16M12 5C9 3 5 3 2 4v15c3-1 7-1 10 2 3-3 7-3 10-2V4c-3-1-7-1-10 1Z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2M8 18h2M14 18h2"/>',
        'calendar-clock' => '<path d="M10 21H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3M8 3v4M16 3v4M3 11h7"/><circle cx="17" cy="17" r="5"/><path d="M17 14v3l2 1"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'file-text' => '<path d="M14 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9Zm0 0v7h7M7 13h10M7 17h10"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-5h6v5M8 7h1M15 7h1M8 11h1M15 11h1"/>',
        'departments' => '<path d="M2 21h20M4 21V9l8-6 8 6v12M8 11v6M12 11v6M16 11v6M3 9h18"/>',
        'workflow' => '<rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="7" height="5" rx="1"/><rect x="15" y="17" width="7" height="5" rx="1"/><path d="M12 7v5M5.5 17v-5h13v5"/>',
        'clipboard-check' => '<rect x="7" y="2" width="10" height="4" rx="1"/><path d="M7 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2m-9 10 3 3 5-5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'pause-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9 8v8M15 8v8"/>',
        'chart' => '<path d="M3 3v18h18M8 16v-5M13 16V7M18 16v-9"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5M9 12h12"/>',
        'printer' => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6ZM18 12h.01"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M3 16v4a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-4"/>',
        'upload' => '<path d="M12 16V3m-5 5 5-5 5 5M3 16v4a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-4"/>',
        'certificate' => '<path d="M10 21H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5M7 6h8M7 10h4"/><circle cx="17" cy="15" r="4"/><path d="m14 18-1 5 4-2 4 2-1-5"/>',
        'alert-triangle' => '<path d="m10.3 4-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-4 8-8 10-4-2-8-5-8-10V6Zm-4 9 3 3 5-6"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    ];
    if (!isset($paths[$name])) {
        throw new LogicException('Unknown interface icon: ' . $name);
    }
    $classes = htmlspecialchars(trim('icon icon-' . $name . ' ' . $class), ENT_QUOTES, 'UTF-8');
    return '<svg xmlns="http://www.w3.org/2000/svg" class="' . $classes . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}
