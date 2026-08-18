# Alasne Notification & Automation Documentation

This directory is the Git-tracked source of truth for the Mission Control notification/event pipeline.

## Documents

- `EVENT-LIFECYCLE.md` — event map, sources, recipients, and idempotency.
- `IMPLEMENTATION-HISTORY.md` — what was built and the key design decisions.
- `PRODUCTION-READINESS-AUDIT.md` — audit findings, fixes, and open risks.
- `DEPLOYMENT-CHECKLIST.md` — dry-run, log-mode, SMTP, scheduler, and rollback checklist.

## Production rule

Business transactions own commerce state. Notifications are downstream side effects and must never roll back checkout/payment, fulfillment, returns/RMA, store credit, or supplier routing/submission.
