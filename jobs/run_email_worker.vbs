Option Explicit

' Task Scheduler uses the windowless script host; PHP never opens a console.
Dim files, projectRoot, xamppRoot, phpPath, iniPath, workerPath, command, shell, result
Set files = CreateObject("Scripting.FileSystemObject")
projectRoot = files.GetParentFolderName(files.GetParentFolderName(WScript.ScriptFullName))
xamppRoot = files.GetParentFolderName(files.GetParentFolderName(projectRoot))
phpPath = files.BuildPath(xamppRoot, "php\php.exe")
If Not files.FileExists(phpPath) Then phpPath = files.BuildPath(xamppRoot, "php\windowsXamppPhp\php.exe")
iniPath = files.BuildPath(xamppRoot, "php\php.ini")
workerPath = files.BuildPath(projectRoot, "jobs\send_notification_emails.php")
If Not files.FileExists(phpPath) Or Not files.FileExists(workerPath) Then WScript.Quit 2
command = Quote(phpPath) & " -c " & Quote(iniPath) & " " & Quote(workerPath)
If WScript.Arguments.Count > 0 Then
    If WScript.Arguments.Count <> 1 Then WScript.Quit 2
    If WScript.Arguments(0) <> "--check" Then WScript.Quit 2
    command = command & " --check"
End If
Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = projectRoot
result = shell.Run(command, 0, True)
WScript.Quit result

Function Quote(value)
    Quote = Chr(34) & value & Chr(34)
End Function
