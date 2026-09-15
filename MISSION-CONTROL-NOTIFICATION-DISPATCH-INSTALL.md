# Mission Control Notification Dispatch Center

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
000051_create_notification_dispatch_center.php
```

## New pages

```text
/admin/notification-dispatches
/admin/notification-dispatches/export
/admin/notification-dispatches/queue
```

## What it adds

This package connects notification templates to the email outbox.

It includes:

- Notification Dispatch Center page
- Test notification queue form
- Enabled template selector
- Recipient input
- Optional custom payload JSON
- Template rendering through the existing template renderer
- Pending email outbox message creation
- Dispatch audit table
- Dispatch event log
- Dispatch CSV export
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_notification_dispatches
mission_control_notification_dispatch_events
```

## Email outbox handling

If the existing `email_outbox` table is missing, this migration creates a simple compatible outbox table:

```text
email_outbox
```

If email_outbox already exists, it is left alone.

The dispatch service writes pending messages into the available outbox columns using common supported column names:

```text
to_email / recipient_email / email / recipient
subject / email_subject
body / body_text / text_body / message
body_html / html_body / html / message_html
status / delivery_status / send_status
created_at / queued_at
updated_at
```

## Safe sending flow

Dispatch does not send email directly.

The flow is:

```text
Template Center
    ↓
Dispatch Center renders template
    ↓
Pending email_outbox message is created
    ↓
Email Queue Processing sends/logs it
    ↓
SMTP transport delivers it when enabled
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
- SMTP credentials
- Scheduled task frequency
- Existing templates
- Existing email queue processor behavior

It adds a controlled bridge from templates to the outbox.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Keep `.env` set to `EMAIL_QUEUE_TRANSPORT=log`.
4. Open `/admin/notification-dispatches`.
5. Choose `Order Confirmation`.
6. Enter your own email address as recipient.
7. Leave payload JSON blank to use the sample payload.
8. Click `Queue Notification`.
9. Confirm a dispatch appears with an outbox ID.
10. Open `/admin/email-queue`.
11. Process a small batch manually with dry-run/log mode.
12. Confirm the email queue attempt appears.
13. When ready, switch to `EMAIL_QUEUE_TRANSPORT=smtp`.
14. Queue one test notification again.
15. Process a small batch manually.
16. Confirm the real email arrives.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control notification dispatch center"
git push
```
