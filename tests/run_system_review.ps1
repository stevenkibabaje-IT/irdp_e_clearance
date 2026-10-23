param([string[]]$Only = @(), [string]$OutputDirectory = '', [int]$StartAt = 0, [string]$TestFilter = '')
$ErrorActionPreference = 'Stop'
$reviewRoot = Split-Path -Parent $PSScriptRoot
$reviewDirectory = if ($OutputDirectory) { $OutputDirectory } else { Join-Path $reviewRoot ('storage\private\system-review-' + (Get-Date -Format 'yyyyMMdd-HHmmss')) }
New-Item -ItemType Directory -Path $reviewDirectory -Force | Out-Null
$env:IRDP_SMTP_ENABLED = '0'
$reviewTests = @('validation_test.php','password_accessibility_test.php','spreadsheet_xml_test.php',
    'parallel_clearance_test.php','finance_payment_migration_test.php','demo_students_test.php',
    'session_security_test.php','email_notifications_test.php','automatic_entry_fee_test.php',
    'clearance_once_test.php','student_password_otp_test.php','reporting_test.php',
    'import_without_runs_test.php','unified_documents_test.php','features_test.php','responsive_test.php')
if ($StartAt) { $reviewTests = $reviewTests | Select-Object -Skip $StartAt }
if ($TestFilter) { $Only = $TestFilter.Split(',') }
if ($Only.Count) { $reviewTests = $reviewTests | Where-Object { $_ -in $Only } }
$reviewResults = @()
if (Test-Path (Join-Path $reviewDirectory 'results.json')) {
    $savedResults = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $reviewDirectory 'results.json')))
    foreach ($savedResult in $savedResults) { $reviewResults += $savedResult }
}
foreach ($reviewTest in $reviewTests) {
    $reviewStart = Get-Date
    $reviewLog = Join-Path $reviewDirectory ($reviewTest + '.log')
    $reviewArguments = @(('"' + (Join-Path $PSScriptRoot $reviewTest) + '"'))
    if ($reviewTest -ne 'unified_documents_test.php') { $reviewArguments += 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4' }
    $reviewErrorLog = $reviewLog + '.stderr'
    $reviewProcess = Start-Process -FilePath C:\xampp\php\php.exe -ArgumentList $reviewArguments -WindowStyle Hidden -RedirectStandardOutput $reviewLog -RedirectStandardError $reviewErrorLog -Wait -PassThru
    $reviewExit = $reviewProcess.ExitCode
    $reviewResults += [pscustomobject]@{Test=$reviewTest;Exit=$reviewExit;Seconds=[math]::Round(((Get-Date)-$reviewStart).TotalSeconds,1);Log=$reviewLog}
    $reviewResults | ConvertTo-Json | Set-Content (Join-Path $reviewDirectory 'results.json')
    Write-Output ($reviewTest + ': exit=' + $reviewExit)
}
Write-Output ('Review logs: ' + $reviewDirectory)
