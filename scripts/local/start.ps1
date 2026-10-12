$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$taskLink = Join-Path $env:USERPROFILE '.cache/lativ-crmeb-phase1'
if (!(Test-Path -LiteralPath $taskLink)) {
    New-Item -ItemType Junction -Path $taskLink -Target $taskRoot | Out-Null
} elseif ((Get-Item -LiteralPath $taskLink).Target -ne $taskRoot) {
    throw 'The local runtime junction points to another workspace.'
}
$taskPhp = Join-Path $taskLink '.local/runtime/php74/php.exe'
$taskDb = Join-Path $taskLink '.local/runtime/mariadb-10.11.10-winx64/bin/mysqld.exe'
foreach ($taskFile in @($taskPhp, $taskDb, (Join-Path $taskRoot '.local/mysql-local.ini'))) {
    if (!(Test-Path -LiteralPath $taskFile)) { throw "Missing local runtime: $taskFile" }
}
$taskSaved = $null
if (Test-Path -LiteralPath (Join-Path $taskRoot '.local/processes.json')) {
    $taskSaved = Get-Content -LiteralPath (Join-Path $taskRoot '.local/processes.json') -Raw | ConvertFrom-Json
}
$taskIds = @{}
foreach ($taskEntry in @(
    @{ Name='db'; Port=13306; Exe=$taskDb; Args=@("--defaults-file=$taskLink/.local/mysql-local.ini"); Cwd="$taskLink/.local" },
    @{ Name='php'; Port=18080; Exe=$taskPhp; Args=@('-S','127.0.0.1:18080','-t','public','../scripts/local/router.php'); Cwd="$taskLink/crmeb" }
)) {
    $taskListener = Get-NetTCPConnection -LocalPort $taskEntry.Port -State Listen -ErrorAction SilentlyContinue
    if ($taskListener) {
        if (!$taskSaved -or $taskListener.OwningProcess -ne $taskSaved.($taskEntry.Name)) {
            throw "Port $($taskEntry.Port) belongs to another process; refusing to replace it."
        }
        $taskProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($taskListener.OwningProcess)"
        if ($taskProcess.CommandLine -notlike "*$taskLink*") { throw 'Unexpected process owner.' }
        $taskIds[$taskEntry.Name] = $taskListener.OwningProcess
        continue
    }
    $taskOptions = @{
        FilePath=$taskEntry.Exe; ArgumentList=$taskEntry.Args; WorkingDirectory=$taskEntry.Cwd
        WindowStyle='Hidden'; PassThru=$true
    }
    if ($taskEntry.Name -eq 'php') {
        $taskOptions.RedirectStandardOutput = "$taskLink/.local/php-server.out.log"
        $taskOptions.RedirectStandardError = "$taskLink/.local/php-server.err.log"
    }
    $taskProcess = Start-Process @taskOptions
    $taskIds[$taskEntry.Name] = $taskProcess.Id
}
$taskIds | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $taskRoot '.local/processes.json') -Encoding ascii
Write-Output 'Local CRMEB: http://127.0.0.1:18080/admin/'
Write-Output 'Credentials remain in the ignored .local/local-access.json file.'
