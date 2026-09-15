# Mission Control Notification Automation Rules

## Install

Extract this ZIP into the Alasne project root:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
php alasne migrate
composer dump-autoload -o
```

This package adds migration:

```text
000052_create_notification_automation_rules.php
```

## New pages

```text
/admin/notification-automations
/admin/notification-automations/export
/admin/notification-automations/RULE_ID
/admin/notification-automations/RULE_ID/test
```

## What it adds

This package adds the control layer for automated notifications.

It includes:

- Notification Automation Rules page
- Event-to-template rule mapping
- Seeded automation rules
- Rules disabled by default
- Dry-run-only mode enabled by default
- Editable recipient source
- Editable default recipient
- Editable payload strategy
- Guardrails JSON
- Manual rule testing
- Dry-run rendering and logging
- Optional queueing through Dispatch Center
- Automation event audit log
- Rule CSV export
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_notification_automation_rules
mission_control_notification_automation_events
```

## Seeded rules

```text
Order Created → Customer Confirmation
Payment Captured → Customer Receipt
Order Shipped → Customer Tracking
Tracking Updated → Customer Notice
Return Requested → Customer Receipt
RMA Approved → Customer Instructions
Store Credit Issued → Customer Notice
Portal Login Requested → Secure Link
Daily Alert Digest → Admin
Purchase Order Created → Supplier Notice
Supplier Submission Failed → Admin Notice
```

## Safe default

Every seeded rule starts as:

```text
is_enabled = 0
dry_run_only = 1
```

That means rules can be tested without queuing or sending real email.

## Safe flow

Dry-run flow:

```text
Rule test
    ↓
Template renders with payload
    ↓
Automation event is logged
    ↓
No outbox message is created
```

Live queueing flow:

```text
Rule enabled
    ↓
Dry Run Only unchecked
    ↓
Rule test or future event hook
    ↓
Dispatch Center queues pending outbox message
    ↓
Email Queue Processing sends/logs
    ↓
SMTP delivers when enabled
```

## Safe scope

This package does not change:

- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Existing templates
- Existing dispatch behavior
- Existing email queue processor behavior
- SMTP settings
- Scheduled task frequency

It adds a controlled rule layer that can later be connected to real platform events.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Keep `.env` set to `EMAIL_QUEUE_TRANSPORT=log`.
4. Open `/admin/notification-automations`.
5. Confirm seeded rules appear.
6. Choose `Order Created → Customer Confirmation`.
7. Add your email as the manual test recipient.
8. Leave `Dry Run Only` checked.
9. Click `Run Test`.
10. Confirm a logged automation event appears.
11. Save the rule with a default recipient.
12. To test queueing, check `Enabled` and uncheck `Dry Run Only`.
13. Click `Run Test` again.
14. Confirm it creates a Dispatch and Outbox ID.
15. Open `/admin/email-queue`.
16. Process the outbox message manually.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control notification automation rules"
git push
```
