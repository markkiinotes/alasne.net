# Tracking Updated Template Contract Repair

Date: **2026-08-21**

## Production-readiness finding

During the controlled Event Bridge lifecycle test, real `tracking.updated`
events reached the Event Bridge and matched their automation rule, but the
rule failed before queueing:

```text
Matched: 1
Queued: 0
Failed: 1
```

The Event Bridge hook itself was working.

The seeded `tracking_updated` template requires:

```text
customer_name
order_number
tracking_status
tracking_number
tracking_url
store_name
```

`TrackingUpdatedNotificationPublisher` supplied every required variable
except:

```text
tracking_status
```

The production-hardening release correctly blocked the live dispatch rather
than allowing an unresolved `{{tracking_status}}` placeholder into email.

## Resolution

The publisher now supplies:

```text
tracking_status = Tracking updated
```

This is deliberately a system-owned event label. Alasne does not yet consume
a real carrier-status feed, so the repair does not invent statuses such as
`In transit`, `Out for delivery`, or `Delivered`.

When a real carrier tracking/status provider is integrated later, the same
payload field can be replaced by the provider's normalized carrier status.

## Database impact

None.

No migration or template rewrite is required.

## Retest expectation

For a new tracking-state fingerprint on an already-shipped order:

```text
event_key = tracking.updated
source = admin_fulfillment
status = completed
matched = 1
queued = 0
dry_run = 1
failed = 0
```

during the controlled dry-run.

## Git commit

After the repair passes:

```powershell
git add app/Services/Notifications/TrackingUpdatedNotificationPublisher.php
git add docs/notifications/TRACKING-UPDATED-CONTRACT-REPAIR.md
git commit -m "Repair tracking updated notification template contract"
git push
```
