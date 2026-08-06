# Mission Control Email Queue Processing

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
000049_create_email_queue_processing.php
```

## New page

```text
/admin/email-queue
/admin/email-queue/export
```

## What it adds

This package adds a controlled email queue processor for Mission Control.

It includes:

- Email queue dashboard
- Pending outbox list
- Manual queue processing
- Safe log-only transport
- Dry-run processing
- Optional PHP `mail()` transport
- Processing attempt audit log
- Attempt CSV export
- Scheduled Operations integration
- Mission Control navigation link
- Mission Control quick-action link

## New table

```text
mission_control_email_queue_attempts
```

## Scheduled operation added

The migration adds this disabled scheduled task:

```text
Email Queue Processor
```

Task type:

```text
email_queue
```

It is disabled by default so you can test the queue manually first.

## Default safe behavior

The default transport is:

```text
log
```

That means messages are processed/logged without sending external email.

To explicitly configure the transport later:

```dotenv
EMAIL_QUEUE_TRANSPORT=log
MAIL_FROM=no-reply@yourdomain.com
```

For PHP mail testing only:

```dotenv
EMAIL_QUEUE_TRANSPORT=php_mail
MAIL_FROM=no-reply@yourdomain.com
```

Keep `log` until your host is configured for real outgoing mail.

## Safe scope

This package does not change:

- Checkout
- Payments
- Supplier routing
- Purchase orders
- Customer orders
- Product records
- Store records
- Customer-facing pages
- Existing alert rules
- Existing briefing snapshots

It only reads the email outbox, updates email processing status where matching columns exist, and records processing attempts.

## Schema tolerance

The processor checks for common email outbox column names, including:

```text
to_email / recipient_email / email / recipient
subject / email_subject
body / body_text / text_body / message
body_html / html_body / html / message_html
status / delivery_status / send_status
created_at / queued_at / send_after / scheduled_at
sent_at / delivered_at / processed_at
attempts / send_attempts / delivery_attempts
last_error / error_message / failure_reason
```

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/email-queue`.
4. Confirm pending outbox messages appear, if any exist.
5. Leave `Dry-run/log only` checked.
6. Click `Process Queue`.
7. Confirm processing attempts appear.
8. Export attempts CSV.
9. Open `/admin/scheduled-operations`.
10. Confirm `Email Queue Processor` appears.
11. Leave it disabled until manual tests are clean.
12. Run it manually from Scheduled Operations.
13. Open `/admin`.
14. Confirm the Mission Control hub has an `Email Queue` link.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control email queue processing"
git push
```
