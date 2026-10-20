<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../storage/private/ar-integration/mail-worker.php';
echo ar_json(ai_mail_worker(ar_db())).PHP_EOL;
