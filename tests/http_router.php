<?php
declare(strict_types=1);
// Development/test server protection equivalent to Apache private-directory rules.
$path=rawurldecode((string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(preg_match('#^/(storage|tests|includes|config|jobs)(/|$)#i',$path) || str_contains($path,'..')){http_response_code(403);exit('Access denied.');}
return false;
