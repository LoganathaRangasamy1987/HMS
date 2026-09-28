$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$runtimeDir = Join-Path $projectRoot '.runtime'
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
$phpArchive = Join-Path $runtimeDir 'php-8.4.25.zip'
$phpDir = Join-Path $runtimeDir 'php'
if (-not (Test-Path (Join-Path $phpDir 'php.exe'))) {
    Invoke-WebRequest -UseBasicParsing -Uri 'https://downloads.php.net/~windows/releases/php-8.4.25-nts-Win32-vs17-x64.zip' -OutFile $phpArchive
    if ((Get-FileHash -LiteralPath $phpArchive -Algorithm SHA256).Hash.ToLowerInvariant() -ne '43a8f67ed2e5223fafb21293c85976361808855405278cef2cf3037c3ae2529c') { throw 'PHP archive checksum mismatch.' }
    Expand-Archive -LiteralPath $phpArchive -DestinationPath $phpDir -Force
}
$composerFile = Join-Path $runtimeDir 'composer.phar'
if (-not (Test-Path $composerFile)) {
    Invoke-WebRequest -UseBasicParsing -Uri 'https://getcomposer.org/download/2.10.3/composer.phar' -OutFile $composerFile
}
if ((Get-FileHash -LiteralPath $composerFile -Algorithm SHA256).Hash.ToLowerInvariant() -ne '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6') { throw 'Composer checksum mismatch.' }
$certFile = Join-Path $runtimeDir 'cacert.pem'
if (-not (Test-Path $certFile)) {
    Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile $certFile
}
$phpConfig = @"
[PHP]
extension_dir="$phpDir\ext"
extension=curl
extension=fileinfo
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=sodium
date.timezone=Asia/Kolkata
memory_limit=512M
upload_max_filesize=10M
post_max_size=12M
display_errors=Off
log_errors=On
curl.cainfo="$certFile"
openssl.cafile="$certFile"
"@
Set-Content -LiteralPath (Join-Path $phpDir 'php.ini') -Value $phpConfig -Encoding Ascii
& (Join-Path $phpDir 'php.exe') -v
if ($LASTEXITCODE -ne 0) { throw 'The local PHP runtime could not start. Check the Visual C++ 2015-2022 x64 runtime.' }
Write-Output 'Project-local PHP and Composer are ready.'
