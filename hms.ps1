param(
    [ValidateSet('serve', 'artisan', 'composer', 'test')][string]$Command = 'serve',
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$CommandArgs
)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpExecutable = Join-Path $PSScriptRoot '.runtime\php\php.exe'
if (-not (Test-Path $phpExecutable)) { throw 'Run scripts/install-tools.ps1 first, or use a system PHP 8.3+ and Composer.' }
$env:PATH = (Split-Path $phpExecutable) + ';' + $env:PATH
$env:COMPOSER_HOME = Join-Path $PSScriptRoot '.runtime\composer-home'
$env:COMPOSER_CACHE_DIR = Join-Path $PSScriptRoot '.runtime\composer-cache'
switch ($Command) {
    'composer' { & $phpExecutable (Join-Path $PSScriptRoot '.runtime\composer.phar') @CommandArgs }
    'artisan' { & $phpExecutable artisan @CommandArgs }
    'test' { & $phpExecutable artisan test @CommandArgs }
    'serve' { & $phpExecutable artisan serve --host=127.0.0.1 --port=8000 @CommandArgs }
}
exit $LASTEXITCODE
