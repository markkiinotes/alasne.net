# Mission Control CLI Automation Runner v2 Hotfix

## What this fixes

This v2 hotfix fixes:

```text
Mission Control CLI Automation Runner failed:
Undefined constant "App\Core\BASE_PATH"
```

Cause:

```text
The CLI runner loads Alasne outside the normal public/index.php web bootstrap.
The web bootstrap defines project-root constants before framework services load.
The CLI runner needed to define those constants itself.
```

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

## Test

Run:

```powershell
php scripts\mission-control-run-due.php --dry-run
```

Then:

```powershell
php scripts\mission-control-run-due.php --due
```

Optional JSON test:

```powershell
php scripts\mission-control-run-due.php --due --json
```

## Commit

```powershell
git status
git add .
git commit -m "Fix Mission Control CLI base path bootstrap"
git push
```
