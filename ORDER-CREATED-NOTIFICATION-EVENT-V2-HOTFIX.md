# Order Created Notification Event Integration v2 Hotfix

## What this fixes

The original order-created integration added this optional fourth dependency to `CheckoutService`:

```php
private ?OrderCreatedNotificationPublisher $orderCreatedNotifications = null
```

On Alasne's custom dependency-injection container, constructor dependencies may be resolved before `createPendingOrder()` executes. That can prevent checkout from reaching the order transaction at all and can cause the storefront controller to redirect back to the storefront home.

The v2 hotfix restores the exact original CheckoutService constructor:

```php
public function __construct(
    private PDO $db,
    private EmailOutboxSender $emailSender,
    private TaxRuleRepository $taxRules
) {
}
```

`OrderCreatedNotificationPublisher` is now instantiated only after:

```php
$this->db->commit();
```

and remains inside its own `try/catch`.

Therefore:

- DI cannot block checkout.
- Notification code cannot run before the order commits.
- Notification failures cannot roll back the order.
- The existing CheckoutService dependency signature is preserved.

## Install

Extract this ZIP over the existing Alasne project:

```text
C:\xampp\htdocs\alasne.net
```

Then run:

```powershell
cd C:\xampp\htdocs\alasne.net
composer dump-autoload -o
```

No migration is required.

## First regression test

Before testing notifications, confirm ordinary checkout works:

1. Leave the order-created rule disabled if desired.
2. Add a product to the storefront cart.
3. Complete checkout.
4. Confirm the order-success/order-tracking flow occurs instead of returning immediately to the storefront homepage.
5. Confirm the order exists in Mission Control.
6. Confirm inventory changed correctly.

## Notification dry-run test

After checkout works:

1. Open `/admin/notification-automations`.
2. Set `Order Created → Customer Confirmation` to Enabled.
3. Keep `Dry Run Only` checked.
4. Place a NEW storefront order.
5. Open `/admin/notification-event-bridge`.
6. Confirm `order.created` with source `storefront_checkout`.
7. Confirm the event item is `dry_run`.

## If checkout still redirects home

Check the Apache/PHP error log immediately after clicking the checkout button and look for either:

```text
Alasne
Checkout
OrderCreatedNotificationPublisher
MissionControlNotificationEventBridge
```

The v2 package removes the new DI constructor dependency, so any remaining redirect would be from the pre-existing checkout/controller redirect path or another runtime error rather than this constructor regression.

## Commit

```powershell
git status
git add .
git commit -m "Fix order created notification checkout dependency"
git push
```
