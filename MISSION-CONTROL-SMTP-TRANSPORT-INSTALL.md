# Mission Control SMTP Email Transport

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

This package depends on the prior email queue package:

```text
000049_create_email_queue_processing.php
```

## New page

```text
/admin/email-delivery
```

## What it adds

This package adds SMTP delivery support for Mission Control email queue processing.

It includes:

- SMTP configuration status page
- SMTP test email form
- SMTP transport service
- Queue processor support for `smtp`
- Delivery attempt logging
- Mission Control navigation link
- Mission Control quick-action link

## Updated queue transports

The email queue processor now supports:

```text
log
php_mail
smtp
```

Keep the queue in `log` mode while testing.

## Recommended .env settings

```dotenv
EMAIL_QUEUE_TRANSPORT=log

SMTP_HOST=smtp.yourprovider.com
SMTP_PORT=587
SMTP_USERNAME=your_username
SMTP_PASSWORD=your_password
SMTP_ENCRYPTION=tls

MAIL_FROM=no-reply@yourdomain.com
MAIL_FROM_NAME="Alasne Mission Control"
```

After the SMTP test succeeds, switch to:

```dotenv
EMAIL_QUEUE_TRANSPORT=smtp
```

## Supported encryption

```text
tls
ssl
none
```

Typical ports:

```text
587 with tls
465 with ssl
25 with none or tls depending on provider
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

It only adds SMTP delivery capability and keeps sending disabled until you configure environment variables and switch the queue transport.

## Test sequence

1. Run `composer dump-autoload -o`.
2. Add SMTP values to `.env`.
3. Keep `EMAIL_QUEUE_TRANSPORT=log`.
4. Open `/admin/email-delivery`.
5. Confirm SMTP status shows configured.
6. Send a test email to yourself.
7. Confirm a delivery attempt appears.
8. After successful test, set `EMAIL_QUEUE_TRANSPORT=smtp`.
9. Open `/admin/email-queue`.
10. Process a small batch manually.
11. Confirm messages are sent and attempts are logged.
12. Only then enable scheduled Email Queue Processor.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control SMTP email transport"
git push
```
