# Notification & Automation Production Readiness Audit

Audit date: **2026-08-17**

## Fixed

### HTML interpolation
Payload values are now HTML-escaped with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Template-owned markup remains intact.

### Subject safety
Rendered subjects remove CR/LF and control characters.

### Template metadata drift
Actual `{{placeholders}}` from subject/text/HTML are merged with `variables_json`.

### Missing variables
Live dispatch is blocked if required variables are missing. Event Bridge and manual automation dry-runs also fail clearly instead of reporting success.

### Admin recipient routing
For `admin_default_recipient`, the automation rule's configured Default Recipient takes precedence over payload fields.

## Verified

- Business transaction / notification failure isolation: PASS
- Explicit per-event idempotency: PASS
- Recipient email validation: PASS
- Safe default email transport (`log`): PASS
- SMTP separated from event/dispatch persistence: PASS
- Current event publishers exclude payment credentials, supplier secrets, and raw supplier payload/response JSON: PASS

## Remaining Legacy Paths

These are not duplicates of the nine seeded events, but remain outside Event Bridge:

- non-tracking fulfillment-only updates;
- return shipping-label/tracking lifecycle emails;
- return received/completed/refund-failed/cancelled emails.

Migrate later as a separate project after the seeded lifecycle is stable.

## Open Operating Constraints

### Email worker concurrency
The queue worker does not currently atomically claim each row before sending.

For initial Windows production:
- run one scheduled worker;
- Task Scheduler: **If the task is already running → Do not start a new instance**;
- avoid manual queue processing while the scheduled worker is running.

Before horizontal scaling, add atomic claiming and stale-worker recovery.

### Retry policy
Failed rows can be retried on later processing runs and attempts are recorded, but no exponential backoff/dead-letter policy exists. Monitor failures during initial production and add retry caps/backoff before high-volume scaling.

### Runtime verification
Run the full deployment checklist after installing this package. Static validation is not a substitute for end-to-end dry-run/log/SMTP testing.

## Release Recommendation

Suitable for a controlled production canary after:
1. hardening install;
2. lifecycle dry-runs;
3. log-mode queue tests;
4. SMTP canary;
5. single-instance scheduler configuration;
6. recipient review.
