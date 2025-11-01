<#
Apply combined SQL migrations helper for Windows PowerShell.

Usage: run from repository root in PowerShell
    .\scripts\apply_migrations.ps1

The script will:
 - Prompt for MySQL user and optionally password (you can just press Enter and mysql client will prompt)
 - Prompt for target database name (default: e_learning)
 - Offer to create the database if it doesn't exist
 - Offer to take an optional mysqldump backup (if mysqldump available)
 - Apply `db/combined_migrations.sql`

Note: Review `db/combined_migrations.sql` before running in production. The file may contain ALTER statements that conflict with an existing schema.
#>

Set-StrictMode -Version Latest

function Prompt-YesNo($msg, $default=$true) {
    $yn = if ($default) {"[Y/n]"} else {"[y/N]"}
    $r = Read-Host "$msg $yn"
    if ([string]::IsNullOrWhiteSpace($r)) { return $default }
    return $r.Trim().ToLower() -in @('y','yes')
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
Push-Location $scriptRoot\.. | Out-Null

$combined = Join-Path -Path (Get-Location) -ChildPath "db\combined_migrations.sql"
if (-not (Test-Path $combined)) {
    Write-Error "Combined migrations file not found: $combined`nCreate or generate db/combined_migrations.sql first."
    Pop-Location | Out-Null
    exit 1
}

$dbName = Read-Host "Target database name (default: e_learning)"
if ([string]::IsNullOrWhiteSpace($dbName)) { $dbName = 'e_learning' }

$mysqlUser = Read-Host "MySQL user (default: root)"
if ([string]::IsNullOrWhiteSpace($mysqlUser)) { $mysqlUser = 'root' }

Write-Host "Using database: $dbName (user: $mysqlUser)"

if (Prompt-YesNo "Create database '$dbName' if it does not exist?" $true) {
    Write-Host "Creating database if needed..."
    # Use a simple CREATE DATABASE command without backtick escaping to avoid passing backslashes to mysql
    & mysql -u $mysqlUser -p -e "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "mysql client returned non-zero exit code ($LASTEXITCODE). You may need to run the CREATE DATABASE command manually."
    }
}

if (Prompt-YesNo "Create a backup of the database before applying migrations?" $false) {
    $dumpFile = Join-Path -Path (Get-Location) -ChildPath "backups\${dbName}_backup_$(Get-Date -Format yyyyMMdd_HHmmss).sql"
    New-Item -ItemType Directory -Path (Split-Path $dumpFile) -Force | Out-Null
    Write-Host "Running mysqldump to: $dumpFile"
    # Use PowerShell pipe to capture output rather than shell redirection
    & mysqldump -u $mysqlUser -p $dbName | Out-File -FilePath $dumpFile -Encoding UTF8
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "mysqldump returned non-zero exit code ($LASTEXITCODE). Backup may have failed."
    } else {
        Write-Host "Backup saved to $dumpFile"
    }
}

Write-Host "Applying combined migrations from: $combined"
# PowerShell doesn't support cmd-style '<' redirection inside scripts; stream file contents into mysql
Get-Content -Raw $combined | & mysql -u $mysqlUser -p $dbName
if ($LASTEXITCODE -ne 0) {
    Write-Warning "mysql returned non-zero exit code ($LASTEXITCODE) while applying migrations."
}
if ($LASTEXITCODE -eq 0) {
    Write-Host "Migrations applied successfully to database '$dbName'."
} else {
    Write-Warning "Migrations completed with warnings/errors. mysql exit code: $LASTEXITCODE"
}

Pop-Location | Out-Null

Write-Host "Done. Review output above for any errors."
