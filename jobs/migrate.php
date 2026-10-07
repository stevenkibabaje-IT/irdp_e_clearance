<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
try{ensure_database_ready();echo "Application migrations completed.\n";}catch(Throwable $e){fwrite(STDERR,"Migration failed. Check MariaDB availability, credentials and database privileges.\n");exit(1);}
