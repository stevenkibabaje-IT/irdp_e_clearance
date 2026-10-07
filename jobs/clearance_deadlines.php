<?php
declare(strict_types=1);

// Keep existing scheduler entries harmless after the feature was removed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}
echo "Automatic clearance deadlines have been removed.\n";
