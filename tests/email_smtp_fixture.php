<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
// Local SMTP sink: never connects to a real email provider.
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$listener) { fwrite(STDERR, "SMTP fixture could not start.\n"); exit(1); }
echo stream_socket_get_name($listener, false), "\n";
flush();
$client = stream_socket_accept($listener, 30);
if (!$client) { exit(1); }
stream_set_timeout($client, 20);
fwrite($client, "220 localhost test SMTP\r\n");
$data = false;
$message = '';
while (($line = fgets($client)) !== false) {
    if ($data) {
        if ($line === ".\r\n") {
            file_put_contents($argv[1], $message);
            fwrite($client, "250 Message accepted\r\n");
            $data = false;
        } else { $message .= $line; }
        continue;
    }
    $command = strtoupper(strtok(trim($line), ' '));
    if ($command === 'EHLO' || $command === 'HELO') { fwrite($client, "250 localhost\r\n"); }
    elseif ($command === 'MAIL' || $command === 'RCPT' || $command === 'RSET') { fwrite($client, "250 OK\r\n"); }
    elseif ($command === 'DATA') { $data = true; fwrite($client, "354 End with a dot\r\n"); }
    elseif ($command === 'QUIT') { fwrite($client, "221 Bye\r\n"); break; }
    else { fwrite($client, "500 Unknown command\r\n"); }
}
fclose($client);
fclose($listener);
