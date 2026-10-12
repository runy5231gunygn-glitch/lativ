$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$taskLink = Join-Path $env:USERPROFILE '.cache/lativ-crmeb-phase1'
$taskFile = Join-Path $taskRoot '.local/processes.json'
if (!(Test-Path -LiteralPath $taskFile)) { return }
$taskIds = Get-Content -LiteralPath $taskFile -Raw | ConvertFrom-Json
foreach ($taskName in @('php', 'db')) {
    $taskId = $taskIds.$taskName
    if (!$taskId) { continue }
    $taskProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$taskId" -ErrorAction SilentlyContinue
    if (!$taskProcess) { continue }
    if ($taskProcess.CommandLine -notlike "*$taskLink*" -or $taskProcess.Name -notin @('php.exe','mysqld.exe')) {
        throw 'Refusing to stop a process outside this local environment.'
    }
    Stop-Process -Id $taskId
}
Write-Output 'Local CRMEB processes stopped; database and credentials retained.'
