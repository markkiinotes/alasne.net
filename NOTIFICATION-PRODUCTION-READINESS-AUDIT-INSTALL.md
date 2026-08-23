# Notification Production Readiness Audit & Hardening

## Install

Extract over:

```text
C:\xampp\htdocs\alasne.net
```

Then:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No migration is required.

## Files changed

```text
app/Services/Admin/MissionControlNotificationTemplateService.php
app/Services/Admin/MissionControlNotificationDispatchService.php
app/Services/Admin/MissionControlNotificationEventBridgeService.php
app/Repositories/MissionControlNotificationEventBridgeRepository.php
app/Repositories/MissionControlEmailQueueRepository.php
app/Services/Admin/MissionControlEmailQueueService.php
```

## Documentation added

```text
docs/notifications/README.md
docs/notifications/EVENT-LIFECYCLE.md
docs/notifications/PRODUCTION-READINESS.md
docs/notifications/TESTING-CHECKLIST.md
docs/notifications/GIT-CHECKLIST.md
docs/notifications/CHANGELOG.md
```

## Optional queue lease setting

```dotenv
EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES=30
```

Default is 30 minutes. Minimum accepted value is 5 minutes.

## First verification

Keep:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Then follow:

```text
docs/notifications/TESTING-CHECKLIST.md
```

## Important

Do not mark customer/browser lifecycle tests passed merely because the backend
publisher or Event Bridge works.

Until the public-facing flow is built, those tests remain:

```text
DEFERRED — STOREFRONT REQUIRED
```

## Git

Follow:

```text
docs/notifications/GIT-CHECKLIST.md
```

Recommended commit:

```powershell
git commit -m "Harden notification automation and document production readiness"
git push
```
