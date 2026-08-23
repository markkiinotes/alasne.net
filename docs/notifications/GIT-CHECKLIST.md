# Git Checklist — Notification Production Readiness

This milestone must be documented in Git.

## Before installation

From:

```powershell
cd C:\xampp\htdocs\alasne.net
```

run:

```powershell
git status
```

If there are unrelated uncommitted changes, review them before extracting the
package. Do not use destructive reset commands just to obtain a clean tree.

## Install package

Extract the package over the Alasne project root, then:

```powershell
composer dump-autoload -o
```

There is no migration in this package.

## Review exactly what changed

```powershell
git status
git diff -- app/Services/Admin/MissionControlNotificationTemplateService.php
git diff -- app/Services/Admin/MissionControlNotificationDispatchService.php
git diff -- app/Services/Admin/MissionControlNotificationEventBridgeService.php
git diff -- app/Repositories/MissionControlNotificationEventBridgeRepository.php
git diff -- app/Repositories/MissionControlEmailQueueRepository.php
git diff -- app/Services/Admin/MissionControlEmailQueueService.php
git diff -- docs/notifications
```

## Recommended commit

After the validation checklist passes:

```powershell
git add app/Services/Admin/MissionControlNotificationTemplateService.php
git add app/Services/Admin/MissionControlNotificationDispatchService.php
git add app/Services/Admin/MissionControlNotificationEventBridgeService.php
git add app/Repositories/MissionControlNotificationEventBridgeRepository.php
git add app/Repositories/MissionControlEmailQueueRepository.php
git add app/Services/Admin/MissionControlEmailQueueService.php
git add docs/notifications
git add NOTIFICATION-PRODUCTION-READINESS-AUDIT-INSTALL.md

git commit -m "Harden notification automation and document production readiness"
git push
```

## Recommended verification after push

```powershell
git status
git log -1 --oneline
```

Expected:

```text
working tree clean
latest commit = notification production readiness milestone
```

## What the commit should communicate

The repository history should make clear that this milestone:

- completed the seeded notification-event wiring audit,
- hardened HTML rendering and required-variable contracts,
- hardened admin-recipient ownership,
- hardened Event Bridge concurrency/idempotency handling,
- hardened Mission Control queue-worker concurrency,
- documented remaining legacy paths,
- documented storefront-dependent tests as deferred.
