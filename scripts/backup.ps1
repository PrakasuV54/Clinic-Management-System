# SQLite Database Automated Backup Script for Crescent Clinic Hospital Portal
# This script checkpoints the SQLite WAL journal and copies the DB file safely.

$ErrorActionPreference = "Stop"

# Define paths
$ProjectRoot = Resolve-Path "$PSScriptRoot\.."
$DbPath = "$ProjectRoot\database\sqlite\hospital_portal.db"
$BackupDir = "$ProjectRoot\backup"

# Create backup directory if it doesn't exist
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
}

Write-Host "Starting automated database backup process..."

# Step 1: Checkpoint WAL journal using PHP PDO to ensure database consistency
try {
    Write-Host "Merging WAL logs to main database file..."
    $PhpCode = "require_once '$ProjectRoot/app/helpers/db.php'; `$db = get_db(); `$db->exec('PRAGMA wal_checkpoint(TRUNCATE)');"
    php -r $PhpCode
    Write-Host "WAL log merge complete."
} catch {
    Write-Warning "Could not merge WAL logs via PHP: $_. Attempting standard copy."
}

# Step 2: Generate timestamped backup filename
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupFile = "$BackupDir\hospital_portal_backup_$Timestamp.db"

# Step 3: Copy the database file
if (Test-Path $DbPath) {
    Copy-Item -Path $DbPath -Destination $BackupFile -Force
    Write-Host "Success: Database backed up to $BackupFile"
} else {
    throw "Database file not found at $DbPath"
}

# Step 4: Clean up backups older than 30 days
$LimitDate = (Get-Date).AddDays(-30)
Get-ChildItem -Path $BackupDir -Filter "hospital_portal_backup_*.db" | Where-Object {
    $_.LastWriteTime -lt $LimitDate
} | Remove-Item -Force
Write-Host "Cleanup of backups older than 30 days complete."
