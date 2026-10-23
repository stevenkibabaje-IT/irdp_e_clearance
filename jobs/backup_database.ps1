param([switch]$Force)

$ErrorActionPreference = 'Stop'
$backupDirectory = 'C:\xampp-backups\irdp_e_clearance'
$dumpExecutable = 'C:\xampp\mysql\bin\mysqldump.exe'
$backupMutex = New-Object Threading.Mutex($false, 'Global\IRDPDatabaseBackup')
$locked = $false
$temporaryPath = $null
try {
    $locked = $backupMutex.WaitOne(0)
    if (-not $locked) { exit 0 }
    New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
    $todayPrefix = 'irdp_all_' + (Get-Date -Format 'yyyyMMdd') + '_'
    if (-not $Force -and (Get-ChildItem -LiteralPath $backupDirectory -Filter ($todayPrefix + '*.sql'))) { exit 0 }
    $backupName = 'irdp_all_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.sql'
    $finalPath = Join-Path $backupDirectory $backupName
    $temporaryPath = $finalPath + '.partial'
    $dumpArguments = @('--host=127.0.0.1', '--port=3306', '--user=root',
        '--all-databases', '--single-transaction', '--quick', '--routines', '--events', '--triggers',
        '--hex-blob', '--default-character-set=utf8mb4', ('--result-file=' + $temporaryPath))
    & $dumpExecutable @dumpArguments 2>> (Join-Path $backupDirectory 'backup-errors.log')
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE; will retry on the next scheduled run." }
    if ((Get-Item -LiteralPath $temporaryPath).Length -lt 100 -or
        -not ((Get-Content -LiteralPath $temporaryPath -Tail 8) -match '^-- Dump completed on ')) {
        throw 'Backup is incomplete; previous backups were preserved.'
    }
    Move-Item -LiteralPath $temporaryPath -Destination $finalPath -ErrorAction Stop
    $temporaryPath = $null
    Add-Content -LiteralPath (Join-Path $backupDirectory 'backup.log') -Value ((Get-Date -Format o) + ' SUCCESS ' + $backupName)
    # Delete only older completed dumps created by this job, after a successful backup.
    Get-ChildItem -LiteralPath $backupDirectory -Filter 'irdp_all_*.sql' |
        Where-Object { $_.Name -match '^irdp_all_\d{8}_\d{6}\.sql$' } |
        Sort-Object Name -Descending | Select-Object -Skip 30 | ForEach-Object {
            $candidate = [IO.Path]::GetFullPath($_.FullName)
            if ([IO.Path]::GetDirectoryName($candidate) -ne $backupDirectory) { throw 'Unexpected cleanup path.' }
            Remove-Item -LiteralPath $candidate -ErrorAction Stop
        }
    Write-Output ('Backup saved: ' + $finalPath)
} catch {
    if (Test-Path -LiteralPath $backupDirectory) {
        Add-Content -LiteralPath (Join-Path $backupDirectory 'backup.log') -Value ((Get-Date -Format o) + ' FAILED ' + $_.Exception.Message)
    }
    Write-Error $_
    exit 1
} finally {
    if ($locked) { $backupMutex.ReleaseMutex() }
    $backupMutex.Dispose()
}
