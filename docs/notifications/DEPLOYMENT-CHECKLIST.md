# Notification Production Deployment Checklist

## 1. Install and lint

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o

php -l app\Services\Admin\MissionControlNotificationTemplateService.php
php -l app\Services\Admin\MissionControlNotificationDispatchService.php
php -l app\Services\Admin\MissionControlNotificationEventBridgeService.php
php -l app\Services\Admin\MissionControlNotificationAutomationService.php
```

Confirm Mission Control, Template Center, Automation Rules, Event Bridge, and Email Queue all load.

## 2. External delivery off

```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Start with every lifecycle rule in Dry Run Only.

For `Supplier Submission Failed → Admin Notice`, set a valid internal Default Recipient.

## 3. Full lifecycle dry-run

Fresh paid order:
```text
order.created
payment.captured
purchase_order.created
```

First shipment:
```text
order.shipped
```

Later carrier/tracking change:
```text
tracking.updated
```

Fresh customer return:
```text
return.requested
```

Approve/RMA:
```text
rma.approved
```

Receive/resolve with store credit:
```text
store_credit.issued
```

Fail a test supplier submission:
```text
supplier_submission.failed
```

Expected for each dry-run:
```text
status = completed
dry_run = 1
queued = 0
```

A missing template variable must now create a failed rule.

## 4. Idempotency

Verify duplicate protection for the same order/payment/shipment/return/RMA/store-credit/PO event, unchanged tracking state, and the same supplier submission attempt. A later supplier attempt may legitimately create a new failure alert.

## 5. Log-mode live queue

One rule at a time:
```text
Enabled = checked
Dry Run Only = unchecked
```

Keep:
```dotenv
EMAIL_QUEUE_TRANSPORT=log
```

Confirm Event Bridge `queued`, Dispatch ID, Outbox ID, and Email Queue `logged`.

## 6. HTML safety canary

Use a test payload value containing:
```text
<script>alert("test")</script> & "quotes"
```

HTML preview/output must display escaped text, never executable markup.

## 7. SMTP canary

Use internal/owned recipients first.

```dotenv
EMAIL_QUEUE_TRANSPORT=smtp
```

Verify:
1. SMTP test;
2. customer-template canary;
3. supplier-template canary;
4. admin failure-alert canary.

## 8. Scheduler

Windows Task Scheduler:
```text
Program:
C:\xampp\php\php.exe

Arguments:
scripts\mission-control-run-due.php --due

Start in:
C:\xampp\htdocs\alasne.net
```

Schedule: every 15 minutes.

Critical setting:
```text
If the task is already running:
Do not start a new instance
```

## 9. Git documentation / release commit

```powershell
git status
git diff --check
git diff
```

Stage:
```powershell
git add app/Services/Admin/MissionControlNotificationTemplateService.php
git add app/Services/Admin/MissionControlNotificationDispatchService.php
git add app/Services/Admin/MissionControlNotificationEventBridgeService.php
git add app/Services/Admin/MissionControlNotificationAutomationService.php
git add docs/notifications
```

Commit and push:
```powershell
git commit -m "Harden notification pipeline for production"
git push
```

Verify:
```powershell
git status
```

Expected:
```text
working tree clean
```

## Rollback

If the hardening release causes a notification-only regression, revert the release commit through normal Git history. Do not roll back commerce/payment/order/return migrations for a notification rendering issue.
