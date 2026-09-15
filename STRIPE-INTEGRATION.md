# Alasne Stripe Integration — Phase 6B.1

Phase 6B.1 installs the Stripe PHP SDK foundation and a signed, idempotent webhook ingestion layer without changing the existing storefront payment path yet.

## Why this phase is separate

Alasne's current checkout performs order creation, provider charge, order payment finalization, and inventory decrement in one synchronous database transaction. Stripe PaymentIntents can require browser-side authentication and can complete asynchronously. Bolting Stripe into the existing synchronous provider call would weaken the checkout guarantees that Phase 5 established.

Phase 6B.1 therefore adds the Stripe foundation first. Phase 6B.2 will refactor the checkout lifecycle into prepare -> confirm -> webhook/finalize while preserving server-owned totals, inventory safety, idempotency, and the existing TestPaymentProvider.

## Secrets

Never commit real Stripe credentials. Configure only in `.env` locally and in the production secret store later:

```dotenv
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Do not paste these values into Git, documentation, screenshots, support tickets, or chat.

## Install SDK

From the Alasne project root:

```cmd
composer require stripe/stripe-php
```

This intentionally lets Composer select the current compatible Stripe PHP SDK version rather than pinning an outdated version in this install package.

## Database

Run:

```cmd
php alasne migrate
```

Migration `000054_create_stripe_webhook_events.php` adds the event ledger used for webhook deduplication, status, retry accounting, and minimal Stripe object references. It deliberately does not duplicate the full Stripe payload into the database.

## Local configuration check

After putting TEST keys in `.env`:

```cmd
php scripts\stripe-check.php
```

Expected shape:

```text
STRIPE_MODE=TEST
SECRET_KEY_CONFIGURED=YES
PUBLISHABLE_KEY_CONFIGURED=YES
WEBHOOK_SECRET_CONFIGURED=YES
STRIPE_ACCOUNT_ID=acct_...
COUNTRY=US
CHARGES_ENABLED=...
PAYOUTS_ENABLED=...
```

The script never prints your API keys.

## Webhook endpoint

```text
POST /webhooks/stripe
```

The endpoint does not use session authentication or CSRF. Stripe authenticates it using the `Stripe-Signature` header and `STRIPE_WEBHOOK_SECRET`.

Phase 6B.1 recognizes:

```text
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
charge.refunded
```

Verified events are recorded idempotently. Duplicate processed events are acknowledged without running twice. Failed events may be retried.

Phase 6B.1 does not modify Alasne order state from Stripe events. That activation happens in Phase 6B.2 after checkout preparation/finalization is refactored safely.

## Stripe CLI local forwarding

When Stripe CLI is installed and authenticated, use test mode and forward events to:

```text
http://alasne.net.local/webhooks/stripe
```

Copy the CLI-provided `whsec_...` signing secret into local `.env` as `STRIPE_WEBHOOK_SECRET`. Do not commit it.

## Acceptance

1. `composer validate --no-check-publish` passes.
2. `composer check-platform-reqs` passes.
3. All new PHP files pass `php -l`.
4. `php alasne migrate` applies migration 000054.
5. `php scripts\stripe-check.php` reaches the Stripe TEST account without printing secrets.
6. A signed Stripe CLI test event reaches `/webhooks/stripe` with HTTP 200.
7. Re-sending the same event does not create a duplicate row.
8. Existing TestPaymentProvider checkout still works unchanged.

Do not enable live mode during Phase 6B.1.
