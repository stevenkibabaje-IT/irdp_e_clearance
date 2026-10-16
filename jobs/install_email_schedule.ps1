$ErrorActionPreference = 'Stop'
$emailProjectRoot = Split-Path -Parent $PSScriptRoot
$emailWorkerPath = Join-Path $emailProjectRoot 'jobs\run_email_worker.vbs'
$emailLegacyWorkerPath = Join-Path $emailProjectRoot 'jobs\run_email_worker.ps1'
$emailScriptHost = Join-Path $env:WINDIR 'System32\wscript.exe'
$emailTaskArguments = '//B //Nologo "' + $emailWorkerPath + '"'
$emailLegacyArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $emailLegacyWorkerPath + '"'
if (-not (Test-Path -LiteralPath $emailScriptHost) -or -not (Test-Path -LiteralPath $emailWorkerPath)) { throw 'The windowless email launcher is unavailable. No task was changed.' }
$emailTaskName = 'IRDP e-Clearance Email Notifications'
$emailExistingTask = Get-ScheduledTask -TaskName $emailTaskName -ErrorAction SilentlyContinue
if ($emailExistingTask -and ($emailExistingTask.Actions.Count -ne 1 -or $emailExistingTask.Actions.Arguments -notin @($emailLegacyArguments,$emailTaskArguments) -or [IO.Path]::GetFileName($emailExistingTask.Actions.Execute) -notin @('powershell.exe','wscript.exe'))) {
    throw 'An unrelated scheduled task already uses this name. No task was changed.'
}
$emailAction = New-ScheduledTaskAction -Execute $emailScriptHost -Argument $emailTaskArguments -WorkingDirectory $emailProjectRoot
$emailTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$emailPrincipal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$emailSettings = New-ScheduledTaskSettingsSet -Hidden -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $emailTaskName -Action $emailAction -Trigger $emailTrigger -Principal $emailPrincipal -Settings $emailSettings -Description 'Send queued IRDP student email notifications every minute while this Windows user is logged in.' -Force | Out-Null
Write-Output 'Installed windowless email delivery task; runs in the background every minute while this Windows user is logged in.'
