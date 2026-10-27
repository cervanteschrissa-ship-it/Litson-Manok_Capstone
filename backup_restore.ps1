# backup_restore.ps1
# Backs up capstone_db, restores it into a separate test database,
# and compares row counts to prove the restore worked.
#
# Requires the PostgreSQL "bin" folder on PATH (pg_dump, pg_restore, psql, createdb, dropdb).
# Run from the project folder:  powershell -ExecutionPolicy Bypass -File .\backup_restore.ps1

param(
    [string]$DbName = "capstone_db",
    [string]$PgUser = "postgres",
    [string]$PgHost = "127.0.0.1",
    [string]$PgPort = "5432"
)

$ErrorActionPreference = "Stop"

$secure = Read-Host "Password for $PgUser" -AsSecureString
$env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

$backupDir = Join-Path $PSScriptRoot "backups"
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

$stamp  = Get-Date -Format "yyyyMMdd_HHmmss"
$file   = Join-Path $backupDir "${DbName}_$stamp.dump"
$testDb = "${DbName}_restore_test"

# 1. BACKUP
Write-Host "`n[1/3] Backing up $DbName to $file"
pg_dump -h $PgHost -p $PgPort -U $PgUser -F c -f $file $DbName
if ($LASTEXITCODE -ne 0) { throw "pg_dump failed" }
Write-Host ("      Backup size: {0:N0} bytes" -f (Get-Item $file).Length)

# 2. RESTORE into a fresh test database
Write-Host "`n[2/3] Restoring into $testDb"
dropdb   -h $PgHost -p $PgPort -U $PgUser --if-exists $testDb
createdb -h $PgHost -p $PgPort -U $PgUser $testDb
pg_restore -h $PgHost -p $PgPort -U $PgUser --no-owner -d $testDb $file
if ($LASTEXITCODE -ne 0) { throw "pg_restore failed" }

# 3. PROVE IT: compare row counts
Write-Host "`n[3/3] Comparing row counts"
$tables = "branches", "production", "sales", "inventory", "inventory_log", "users"
$allMatch = $true

foreach ($t in $tables) {
    $a = (psql -h $PgHost -p $PgPort -U $PgUser -d $DbName -t -A -c "SELECT count(*) FROM $t").Trim()
    $b = (psql -h $PgHost -p $PgPort -U $PgUser -d $testDb -t -A -c "SELECT count(*) FROM $t").Trim()

    if ($a -eq $b) { $status = "MATCH" } else { $status = "DIFFERENT"; $allMatch = $false }
    "{0,-15} original={1,-6} restored={2,-6} {3}" -f $t, $a, $b, $status
}

if ($allMatch) {
    Write-Host "`nRestore proven: every table has the same row count." -ForegroundColor Green
} else {
    Write-Host "`nRestore problem: some row counts differ." -ForegroundColor Red
}

Remove-Item Env:\PGPASSWORD
