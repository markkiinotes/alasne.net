# Notification Production Readiness Audit

Audit date: **2026-08-23**

## Result summary

### PASS / already established

- Notification templates have seeded system templates and variables.
- Automation rules are disabled/dry-run by default.
- Dispatch Center queues to `email_outbox` instead of sending directly.
- SMTP transport has already been proven against the configured business SMTP account.
- Event Bridge tables have unique run/item idempotency keys.
- All nine seeded lifecycle/operational event publishers are wired.
- Publisher failures are isolated from already-committed business transactions.
- Supplier/customer publisher payloads intentionally exclude payment credentials and integration secrets.

### HARDENED by this audit

1. **HTML value escaping**
   - Payload variables inserted into `body_html` are escaped with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
   - Template-authored HTML stays raw.

2. **Header safety**
   - Rendered email subjects collapse CR/LF after substitution.

3. **Template contract drift**
   - Required-variable validation uses the union of `variables_json` and placeholders actually found in subject/text/HTML.
   - Missing variables fail both live dispatch and Event Bridge dry-run validation.

4. **Admin-recipient ownership**
   - `admin_default_recipient` requires the rule's configured Default Recipient.
   - Event payload fields cannot silently redirect internal operations alerts.

5. **Concurrent duplicate events**
   - The service still performs a fast duplicate lookup.
   - If two events race and the database unique key rejects one insert, the loser returns `duplicate` instead of surfacing a database error.

6. **Concurrent queue workers**
   - Mission Control workers atomically move an eligible row to `processing` before sending/logging.
   - Another worker that read the same snapshot skips that row.

7. **Stale queue processing leases**
   - `processing` rows older than the lease timeout are moved to `failed` and become retryable.
   - Configure with:
     `EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES=30`

## Known production characteristics

### SMTP delivery is at-least-once at the crash boundary

No SMTP client can perfectly know whether a remote server accepted a message
if the worker dies at the exact wrong point after transmission. The queue
claim prevents normal concurrent duplication, but a stale-lease retry after
a process crash can theoretically send a second copy.

This is documented rather than hidden.

### Failed queue rows are retryable

The existing queue intentionally includes failed rows as eligible work.
This audit preserves that behavior.

A future milestone may add:

- maximum automatic attempts,
- exponential backoff,
- manual retry/reset controls,
- dead-letter state.

Those are operational improvements, not blockers for the current audit.

### Legacy direct notification paths still exist

Not every older mail helper is removed.

Known examples include:

- generic admin order-status update emails,
- fulfillment-only edits that are neither first shipment nor tracking-state change,
- general return-completion/resolution messages,
- older private helper methods that may no longer be called.

These are not automatically duplicates of the Event Bridge events.

Before removing any legacy path, verify the exact trigger and message purpose.
Do not delete them merely because they also use `email_outbox`.

## Storefront-dependent verification

The following cannot be honestly certified through a finished public customer
experience yet:

```text
DEFERRED — STOREFRONT REQUIRED
- polished public catalog → cart → checkout journey
- customer-facing payment UX
- customer account/order-history UX
- polished public tracking UX
- polished public return/RMA UX
```

Backend publishers and Mission Control integrations can still be tested
independently.

## Production gate

Before changing broad notification automations from dry-run to live SMTP:

1. Keep `EMAIL_QUEUE_TRANSPORT=log`.
2. Validate each enabled rule with controlled test records.
3. Confirm recipient addresses.
4. Confirm rendered subject/text/HTML.
5. Confirm idempotency with a repeated identical event.
6. Confirm missing-variable failure behavior.
7. Confirm queue worker claiming with two near-simultaneous worker starts if practical.
8. Review the outbox and Event Bridge audit history.
9. Only then switch selected rules to SMTP.
