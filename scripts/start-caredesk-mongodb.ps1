param(
    [string]$MongoExecutable = 'C:\Program Files\MongoDB\Server\8.2\bin\mongod.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimePath = Join-Path $projectRoot '.runtime\mongodb-replica'
$dataPath = Join-Path $runtimePath 'data'
$logPath = Join-Path $runtimePath 'mongod.log'
$pidPath = Join-Path $runtimePath 'mongod.pid'

New-Item -ItemType Directory -Path $dataPath -Force | Out-Null

if (Test-Path $pidPath) {
    $existingPid = [int](Get-Content $pidPath)
    if (Get-Process -Id $existingPid -ErrorAction SilentlyContinue) {
        Write-Output "CareDesk MongoDB replica process is already running (PID $existingPid)."
        exit 0
    }
}

$process = Start-Process -FilePath $MongoExecutable -ArgumentList '--dbpath', $dataPath, '--port', '27018', '--bind_ip', '127.0.0.1', '--replSet', 'caredesk-rs', '--logpath', $logPath, '--logappend' -WindowStyle Hidden -PassThru
Set-Content -LiteralPath $pidPath -Value $process.Id
Write-Output "Started CareDesk MongoDB replica process (PID $($process.Id), port 27018)."
