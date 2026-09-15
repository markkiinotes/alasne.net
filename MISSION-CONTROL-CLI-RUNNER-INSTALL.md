# Mission Control CLI Automation Runner

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No database migration is required.

This package depends on the previous Scheduled Operations milestone:

```text
000048_create_mission_control_scheduled_operations.php
```

## What it adds

This package adds CLI scripts that can run Mission Control scheduled operations outside the browser.

Files added:

```text
scripts/mission-control-run-due.php
scripts/mission-control-run-due.bat
scripts/mission-control-run-due.ps1
config/mission-control-scheduler.example.json
MISSION-CONTROL-CLI-RUNNER-INSTALL.md
```

## CLI commands

Run due operations:

```powershell
php scripts\mission-control-run-due.php --due
```

Dry run:

```powershell
php scripts\mission-control-run-due.php --dry-run
```

Run one scheduled task by ID:

```powershell
php scripts\mission-control-run-due.php --task=1
```

JSON output:

```powershell
php scripts\mission-control-run-due.php --due --json
```

Help:

```powershell
php scripts\mission-control-run-due.php --help
```

## Windows Task Scheduler setup

Use these settings:

```text
Program/script:
C:\xampp\php\php.exe

Add arguments:
scripts\mission-control-run-due.php --due

Start in:
C:\xampp\htdocs\alasne.net
```

Recommended trigger:

```text
Every 15 minutes
```

This lets Mission Control decide what is actually due based on each task's `next_run_at`.

## Optional BAT wrapper

You can also point Windows Task Scheduler at:

```text
C:\xampp\htdocs\alasne.net\scripts\mission-control-run-due.bat
```

The BAT file assumes `php` is available in PATH. If not, edit it and replace:

```text
php
```

with:

```text
C:\xampp\php\php.exe
```

## Optional PowerShell wrapper

```powershell
powershell -ExecutionPolicy Bypass -File scripts\mission-control-run-due.ps1
```

## Linux cron example

```cron
*/15 * * * * cd /var/www/alasne.net && php scripts/mission-control-run-due.php --due >> storage/logs/mission-control-scheduler.log 2>&1
```

## Safe scope

This package does not change:

- Database schema
- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Customer-facing pages
- Alert rules
- Briefing snapshots

It only adds CLI scripts that call the existing Mission Control Scheduled Operations service.

## Test sequence

1. Extract the package.
2. Run `composer dump-autoload -o`.
3. Run:

```powershell
php scripts\mission-control-run-due.php --dry-run
```

4. Confirm due tasks are listed or zero due tasks are shown.
5. Run:

```powershell
php scripts\mission-control-run-due.php --due
```

6. Open `/admin/scheduled-operations`.
7. Confirm a scheduled run record appears.
8. Run one task manually:

```powershell
php scripts\mission-control-run-due.php --task=1
```

9. Confirm another run record appears.
10. Set up Windows Task Scheduler when ready.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control CLI automation runner"
git push
```
