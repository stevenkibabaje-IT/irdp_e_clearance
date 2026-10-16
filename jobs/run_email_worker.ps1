param([switch]$Check)
$ErrorActionPreference = 'Stop'
$emailProjectRoot = Split-Path -Parent $PSScriptRoot
$emailXamppRoot = Split-Path -Parent (Split-Path -Parent $emailProjectRoot)
$emailPhpCandidates = @(
    (Join-Path $emailXamppRoot 'php\php.exe'),
    (Join-Path $emailXamppRoot 'php\windowsXamppPhp\php.exe')
)
$emailPhpBinary = $emailPhpCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $emailPhpBinary) { throw 'PHP CLI could not be found in this XAMPP installation.' }
$emailPhpIni = Join-Path $emailXamppRoot 'php\php.ini'
$emailWorkerArguments = @('-c', $emailPhpIni, (Join-Path $emailProjectRoot 'jobs\send_notification_emails.php'))
if ($Check) { $emailWorkerArguments += '--check' }
& $emailPhpBinary @emailWorkerArguments
exit $LASTEXITCODE
