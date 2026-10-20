Option Explicit
Dim emailFiles, emailShell, emailFolder, emailWorker, emailPowerShell, emailCommand
Set emailFiles = CreateObject("Scripting.FileSystemObject")
Set emailShell = CreateObject("WScript.Shell")
emailFolder = emailFiles.GetParentFolderName(WScript.ScriptFullName)
emailWorker = emailFiles.BuildPath(emailFolder, "run_email_worker.ps1")
emailPowerShell = emailShell.ExpandEnvironmentStrings("%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe")
emailCommand = Chr(34) & emailPowerShell & Chr(34) & " -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File " & Chr(34) & emailWorker & Chr(34)
WScript.Quit emailShell.Run(emailCommand, 0, True)
