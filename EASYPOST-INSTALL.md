# EasyPost live carrier integration

1. Run `php alasne migrate`.
2. Run `composer dump-autoload -o`.
3. Add `EASYPOST_API_KEY` and `EASYPOST_WEBHOOK_SECRET` to `.env`.
4. Open Mission Control > Stores > Manage Carrier Integration.
5. Configure the structured return address and enable Test mode.
6. In EasyPost, create a webhook for the displayed public HTTPS URL and use the same webhook secret.
7. Request rates on an approved RMA, buy a test label, and use EasyPost test tracking codes/webhooks.

The webhook endpoint verifies `X-Hmac-Signature` as `hmac-sha256-hex=` plus the HMAC-SHA256 hex digest of the raw request body.

## Test tracking codes
EasyPost documents `EZ1000000001` through `EZ7000000007` for simulated tracker states. Purchased labels automatically create trackers. Webhooks are idempotent by EasyPost event ID.

## Purchase recovery
Rate purchase rows are claimed before the network request. A retry first retrieves the EasyPost shipment; if the provider already purchased it, Alasne records the existing label rather than buying it again.
