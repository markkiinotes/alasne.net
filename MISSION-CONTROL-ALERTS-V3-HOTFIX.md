# Mission Control Alerts & Notification Center v3 Hotfix

## What this fixes

This v3 hotfix fixes this error when running an alert scan:

```text
SQLSTATE[HY093]: Invalid parameter number
```

Cause:

```text
PDO/MySQL can reject SQL statements that reuse the same named placeholder more than once.
```

The alert scan checked for an existing open alert using repeated placeholders:

```text
:store_id
:supplier_id
```

The v3 fix replaces that logic with MySQL null-safe equality:

```sql
store_id <=> :store_id
supplier_id <=> :supplier_id
```

It also patches the alert status update query so it does not reuse the `:status`
placeholder inside CASE expressions.

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

If migration `000046` already ran, you do not need to rerun it. It is safe to run:

```powershell
php alasne migrate
```

## Test

1. Open `/admin`.
2. Confirm Mission Control loads.
3. Open `/admin/alerts`.
4. Click `Run Alert Scan`.
5. Confirm the HY093 error is gone.
6. Acknowledge an alert.
7. Resolve an alert with a note.
8. Export alerts CSV.

## Commit

```powershell
git status
git add .
git commit -m "Fix Mission Control alert scan parameter binding"
git push
```
