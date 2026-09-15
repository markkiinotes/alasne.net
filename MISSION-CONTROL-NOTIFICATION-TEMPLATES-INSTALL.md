# Mission Control Notification Template Center

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
000050_create_notification_templates.php
```

## New pages

```text
/admin/notification-templates
/admin/notification-templates/export
/admin/notification-templates/TEMPLATE_ID
/admin/notification-templates/TEMPLATE_ID/preview
```

## What it adds

This package adds a Mission Control center for managing notification templates.

It includes:

- Seeded customer email templates
- Seeded supplier email templates
- Seeded admin alert templates
- Template list and filters
- Editable subject, text body, and HTML body
- Placeholder variables using `{{variable_name}}`
- Sample payload JSON
- Custom preview JSON
- Rendered subject/text/HTML preview
- Template version history
- Template event log
- CSV export
- Mission Control navigation link
- Mission Control quick-action link

## New tables

```text
mission_control_notification_templates
mission_control_notification_template_versions
mission_control_notification_template_events
```

## Seeded templates

```text
Order Confirmation
Payment Received
Order Shipped
Tracking Updated
Return Request Received
RMA Approved
Store Credit Issued
Customer Portal Login Link
Admin Alert Digest
Supplier Purchase Order Notification
Failed Supplier Submission Notice
```

## Placeholder syntax

Use double braces:

```text
{{customer_name}}
{{order_number}}
{{tracking_number}}
{{store_name}}
```

Example:

```text
Hi {{customer_name}},

Your order {{order_number}} has shipped.

Tracking: {{tracking_number}}
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
- Existing email queue sends
- SMTP settings
- Scheduled jobs
- Customer-facing pages

This milestone manages and previews notification content. The next milestone can connect these templates to the email outbox/event system.

## Test sequence

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Open `/admin/notification-templates`.
4. Confirm seeded templates appear.
5. Open `Order Confirmation`.
6. Confirm subject, text body, HTML body, variables, and sample payload appear.
7. Click `Preview With JSON`.
8. Edit a small wording change.
9. Add a change note.
10. Save the template.
11. Confirm version history updates.
12. Export templates CSV.
13. Open `/admin`.
14. Confirm the Mission Control hub has a `Templates` link.

## Commit

```powershell
git status
git add .
git commit -m "Add Mission Control notification template center"
git push
```
