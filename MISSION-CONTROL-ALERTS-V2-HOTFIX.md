# Mission Control Alerts & Notification Center v2 Hotfix

## What this fixes

This v2 hotfix fixes:

```text
Call to undefined method App\Services\Admin\MissionControlNavigationService::openMissionControlAlerts()
```

The original package added the Alerts navigation badge call but did not correctly insert the helper method into `MissionControlNavigationService.php`.

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

If migration `000046` already ran, you do not need to rerun it. It is safe to run:

```powershell
php alasne migrate
```

## Test

1. Open `/admin`.
2. Confirm the Mission Control hub loads.
3. Open `/admin/alerts`.
4. Click `Run Alert Scan`.
5. Return to `/admin`.
6. Confirm the Alerts badge/navigation no longer throws a fatal error.

## Commit

```powershell
git status
git add .
git commit -m "Fix Mission Control alerts navigation helper"
git push
```
