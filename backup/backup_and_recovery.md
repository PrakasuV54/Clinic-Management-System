# Crescent Clinic & Scans — Database Backup & Recovery Procedures

This document outlines the procedures for automated backups, manual backups, backup integrity verification, and recovery processes for the Crescent Clinic Hospital Portal.

---

## 1. Automated Daily Backup

Automated backups are configured via a PowerShell script `scripts/backup.ps1` which handles WAL checkpointing and creates timestamped file copies in the `backup/` directory.

### Configuration via Windows Task Scheduler
To automate this script to run daily at 11:59 PM:
1. Open **Task Scheduler** on the Windows Host.
2. Click **Create Basic Task...**
3. Name: `CrescentClinic_DB_Backup`
4. Trigger: **Daily** (set to Recur every 1 day at 23:59:00).
5. Action: **Start a program**
6. Program/script: `powershell.exe`
7. Add arguments: `-ExecutionPolicy Bypass -File "C:\Users\Srimathi\Downloads\hospital_portal\hospital_portal for last update 18-6-2026\scripts\backup.ps1"`
8. Start in: `C:\Users\Srimathi\Downloads\hospital_portal\hospital_portal for last update 18-6-2026`
9. Under **Conditions**, ensure it runs regardless of power source (plugged in or battery).
10. Click **Finish**.

---

## 2. Backup Integrity Verification Procedure

Backups must be verified regularly to guarantee that they are not corrupted and can be successfully restored.

### Step-by-Step Verification:
1. Locate the latest backup file in the `backup/` folder (e.g. `hospital_portal_backup_20260622_235701.db`).
2. Open a command prompt / PowerShell window.
3. Run the following integrity check using the SQLite CLI:
   ```cmd
   sqlite3.exe backup\hospital_portal_backup_20260622_235701.db "PRAGMA integrity_check;"
   ```
   *Expected Output:* `ok`
4. If `sqlite3.exe` is not globally installed, you can use the built-in PHP interpreter:
   ```powershell
   php -r "$db = new PDO('sqlite:backup/hospital_portal_backup_20260622_235701.db'); print_r($db->query('PRAGMA integrity_check;')->fetchAll());"
   ```
   *Expected Output:* `Array ( [0] => Array ( [integrity_check] => ok [0] => ok ) )`

---

## 3. Database Recovery Procedure (Restore)

In the event of database corruption or data loss, follow these instructions to restore from a known good backup.

### Step-by-Step Restore:
1. Stop the web server (e.g. stop PHP development server or IIS/Apache).
2. Navigate to the database directory: `database/sqlite/`.
3. Rename the current database file to preserve it for forensic analysis:
   ```powershell
   Rename-Item -Path "database\sqlite\hospital_portal.db" -NewName "hospital_portal_corrupted_$(Get-Date -Format 'yyyyMMdd_HHmmss').db"
   ```
4. Copy the selected backup file from `backup/` into the database directory and name it `hospital_portal.db`:
   ```powershell
   Copy-Item -Path "backup\hospital_portal_backup_20260622_235701.db" -Destination "database\sqlite\hospital_portal.db" -Force
   ```
5. Remove any leftover journal or WAL files to prevent conflicts:
   ```powershell
   Remove-Item -Path "database\sqlite\hospital_portal.db-wal" -ErrorAction SilentlyContinue
   remove-Item -Path "database\sqlite\hospital_portal.db-shm" -ErrorAction SilentlyContinue
   ```
6. Start the web server.
7. Verify that the system loads and all patient and billing records are visible and consistent.
