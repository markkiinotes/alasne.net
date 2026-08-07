# Mission Control Notification Template Center v3 Hotfix

## What this fixes

This v3 hotfix cleans up template-center text overflow.

It fixes overhanging text around:

```text
Version History
Preview boxes
HTML preview content
Variable badges
Long URLs
Long template keys
Long table cells
JSON/text fields
Right-side editor panels
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
2. Open a template such as `Order Confirmation`.
3. Confirm the right-side preview panels stay inside their boxes.
4. Confirm Available Variables do not overhang.
5. Confirm Version History text stays inside the Version History box.
6. Resize the browser smaller and confirm the page still wraps cleanly.

## Commit

```powershell
git status
git add .
git commit -m "Clean up notification template layout overflow"
git push
```
