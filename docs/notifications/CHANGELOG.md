# Notification Changelog

## 2026-08-23 — Production Readiness Audit & Hardening

### Added

- Git-tracked notification architecture documentation.
- Event lifecycle and idempotency map.
- Backend/admin testing checklist.
- Explicit storefront-dependent deferred-test list.
- Git installation/review/commit checklist.

### Security / correctness hardening

- Escape payload substitutions in HTML templates.
- Collapse CR/LF in rendered subject headers.
- Validate actual template placeholders in addition to `variables_json`.
- Reject missing template variables during dry-run and live queueing.
- Require configured rule recipient for `admin_default_recipient`.
- Convert Event Bridge duplicate-key races into clean duplicate results.
- Add atomic Mission Control email queue row claims.
- Add stale `processing` lease recovery.

### Preserved

- Existing template-authored HTML.
- Existing business event publishers.
- Existing automation rule schema.
- Existing Event Bridge schema and database unique idempotency keys.
- Existing `email_outbox` schema.
- Existing log/SMTP transports.
- Existing retry eligibility of failed email rows.

### Deferred

- Public storefront/customer UX verification.
- Dead-letter/max-attempt/backoff controls.
- Broad removal of legacy direct email helpers.
