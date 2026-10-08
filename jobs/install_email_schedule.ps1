$ErrorActionPreference = 'Stop'
$emailProjectRoot = Split-Path -Parent $PSScriptRoot
$emailWorkerPath = Join-Path $emailProjectRoot 'jobs\run_email_worker.ps1'
$emailTaskName = 'IRDP e-Clearance Email Notifications'
$emailExistingTask = Get-ScheduledTask -TaskName $emailTaskName -ErrorAction SilentlyContinue
if ($emailExistingTask -and -not ($emailExistingTask.Actions.Arguments -contains ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $emailWorkerPath + '"'))) {
    throw 'An unrelated scheduled task already uses this name. No task was changed.'
}
$emailAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $emailWorkerPath + '"') -WorkingDirectory $emailProjectRoot
$emailTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$emailPrincipal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$emailSettings = New-ScheduledTaskSettingsSet -Hidden -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $emailTaskName -Action $emailAction -Trigger $emailTrigger -Principal $emailPrincipal -Settings $emailSettings -Description 'Send queued IRDP student email notifications every minute while this Windows user is logged in.' -Force | Out-Null
Write-Output 'Installed email delivery task; runs every minute while this Windows user is logged in.'
