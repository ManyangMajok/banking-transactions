$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$mysqlBinary = Join-Path $projectRoot '.runtime/mysql-8.4.11-winx64/bin/mysqld.exe'
$mysqlData = Join-Path $projectRoot '.runtime/mysql-data'
if ((Test-Path -LiteralPath $mysqlBinary) -and (Test-Path -LiteralPath $mysqlData)) {
    $listener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
    if (-not $listener) {
        $mysqlBase = Join-Path $projectRoot '.runtime/mysql-8.4.11-winx64'
        $mysqlArguments = @('--no-defaults', "--basedir=`"$mysqlBase`"", "--datadir=`"$mysqlData`"", '--port=3307', '--bind-address=127.0.0.1', '--mysqlx=0')
        Start-Process -FilePath $mysqlBinary -ArgumentList $mysqlArguments -WorkingDirectory $projectRoot -WindowStyle Hidden
    }
}
Set-Location -LiteralPath $projectRoot
Write-Host 'Starting Kijani Banking at http://127.0.0.1:8000. Press Ctrl+C to stop PHP.'
php artisan serve --host=127.0.0.1 --port=8000
