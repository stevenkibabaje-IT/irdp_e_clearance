$ErrorActionPreference = 'Stop'
$emailProjectRoot = Split-Path -Parent $PSScriptRoot
& 'C:\xampp\php\php.exe' (Join-Path $emailProjectRoot 'jobs\send_notification_emails.php') | Out-Null
exit $LASTEXITCODE
