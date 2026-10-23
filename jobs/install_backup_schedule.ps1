$ErrorActionPreference = 'Stop'
$backupScript = Join-Path $PSScriptRoot 'backup_database.ps1'
$scheduler = New-Object -ComObject 'Schedule.Service'
$scheduler.Connect()
$taskFolder = $scheduler.GetFolder('\')
$taskName = 'IRDP e-Clearance Database Backup'
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $backupScript + '"'
$existing = @($taskFolder.GetTasks(0)) | Where-Object { $_.Name -eq $taskName }
if ($existing -and $existing.Definition.Actions.Item(1).Arguments -ne $arguments) {
    throw 'An unrelated task uses this name; no task was changed.'
}
$definition = $scheduler.NewTask(0)
$definition.RegistrationInfo.Description = 'Back up all local XAMPP databases once daily; check hourly and retry when MySQL returns. Keep the last 30 completed backups outside the web root.'
$backupUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$definition.Principal.UserId = $backupUser
$definition.Principal.LogonType = 3
$definition.Settings.Enabled = $true
$definition.Settings.Hidden = $true
$definition.Settings.StartWhenAvailable = $true
$definition.Settings.DisallowStartIfOnBatteries = $false
$definition.Settings.StopIfGoingOnBatteries = $false
$definition.Settings.MultipleInstances = 2
$definition.Settings.ExecutionTimeLimit = 'PT30M'
$trigger = $definition.Triggers.Create(2)
$trigger.StartBoundary = (Get-Date).AddMinutes(1).ToString('yyyy-MM-ddTHH:mm:ss')
$trigger.DaysInterval = 1
$trigger.Repetition.Interval = 'PT1H'
$trigger.Repetition.Duration = 'P1D'
$trigger.Enabled = $true
$action = $definition.Actions.Create(0)
$action.Path = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
$action.Arguments = $arguments
$action.WorkingDirectory = Split-Path -Parent $PSScriptRoot
$taskFolder.RegisterTaskDefinition($taskName, $definition, 6, $backupUser, $null, 3) | Out-Null
Write-Output ('Installed: ' + $taskName)
