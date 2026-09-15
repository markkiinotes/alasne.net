# Mission Control Notification Template Center v2 Hotfix

## What this fixes

This v2 hotfix fixes unreadable white text on white backgrounds in the Notification Template Center.

Affected pages:

```text
/admin/notification-templates
/admin/notification-templates/TEMPLATE_ID
```

Cause:

```text
The admin theme can inherit light text colors into form fields and preview boxes.
The v2 views force dark readable text inside the template center UI.
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

1. Open `/admin/notification-templates`.
2. Confirm table text, filter text, and create-template fields are readable.
3. Open a template such as `Order Confirmation`.
4. Confirm subject/body fields, JSON fields, preview panels, version history, and buttons are readable.

## Commit

```powershell
git status
git add .
git commit -m "Fix notification template text readability"
git push
```
