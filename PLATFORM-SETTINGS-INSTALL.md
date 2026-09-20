PLATFORM SETTINGS FOUNDATION
============================

PURPOSE
-------
Create a secure, auditable global settings layer for Alasne before
building the AI Engine and public platform homepage.

This module is developed and accepted locally in XAMPP first. The
same code can later be deployed to Turbify, where production values
will be stored in the production database and production secrets will
remain protected environment variables.

SCOPE
-----
Platform Settings manages global, non-secret defaults only.

Store-specific settings remain in their existing store modules,
including:
- payment methods
- tax rules
- shipping methods
- return policy
- supplier configuration
- storefront catalog configuration

Secrets remain environment variables and are NOT stored as platform
setting values, including:
- database passwords
- APP_KEY
- Stripe secret keys
- Stripe webhook secrets
- SMTP passwords
- AI API keys
- supplier API credentials

Future integrations may register an environment_reference setting.
That value stores only the environment-variable NAME, never its secret.

BRANCH
------
feature/platform-settings-foundation

Base checkpoint:
146039a85475c2037896939d2838af204c0b6642
Close Stripe operations recovery acceptance

MIGRATION
---------
000056_create_platform_settings.php

Creates:
- platform_settings
- platform_setting_audit_log

Seeds/upserts permission:
- settings.manage

The permission is attached to super_admin when that role exists.

The migration intentionally does not remove settings.manage during
rollback because some Alasne installations may already have that
permission from earlier manual role/permission setup.

INITIAL SETTINGS
----------------
General:
- platform.name
- platform.support_email

Regional Defaults:
- platform.timezone
- platform.currency
- platform.locale

Administration:
- admin.default_page_size

These settings do not rewrite existing order/store snapshots.

SUPPORTED VALUE TYPES
---------------------
The service supports:
- string
- text
- email
- timezone
- select
- integer
- decimal
- boolean
- environment_reference

Definitions are database-driven using:
- value_type
- options_json
- validation_json
- is_editable
- sort_order

Future modules can register additional settings through migrations
without adding one-off validation code to the controller.

AUDIT LOG
---------
Every actual value change records:
- setting key
- old value
- new value
- operator user ID/name when available
- change source
- timestamp

Submitting the same value does not create an audit row.

MISSION CONTROL
---------------
New routes:

GET  /admin/settings
POST /admin/settings

Both require:
- authenticated session
- settings.manage

The existing admin sidebar already contains the Settings link behind
settings.manage. Migration 000056 makes that dormant link functional.

Mission Control workflow navigation also exposes:
Platform Settings

SERVICE API
-----------
Future modules can read typed settings through:

App\Services\Settings\PlatformSettingsService

Example:

$settings = app()->container->make(
    App\Services\Settings\PlatformSettingsService::class
);

$name = $settings->get(
    'platform.name',
    'Alasne Platform'
);

$pageSize = $settings->get(
    'admin.default_page_size',
    25
);

No current payment, checkout, refund, fulfillment, or return behavior
is changed by this foundation.

LOCAL INSTALL / CODE GATE
-------------------------
From:

C:\xampp\htdocs\alasne.net

run:

git fetch origin
git switch --track origin/feature/platform-settings-foundation

php -l database\migrations\000056_create_platform_settings.php
php -l app\Repositories\PlatformSettingRepository.php
php -l app\Services\Settings\PlatformSettingsService.php
php -l app\Controllers\Admin\PlatformSettingsController.php
php -l app\Views\admin\settings\index.php
php -l app\Services\Admin\MissionControlNavigationService.php
php -l config\routes.php

composer dump-autoload -o
git diff --check
php alasne migrate

Then:

powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-stage.ps1

Expected:
- all PHP lint checks pass
- Composer optimized autoload succeeds
- git diff --check prints nothing
- migration 000056 completes
- Stage build verification PASSED.

ACCEPTANCE A - SCHEMA + PERMISSION
--------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); $sql='SELECT setting_key,group_key,label,value_type,value_text FROM platform_settings ORDER BY group_key,sort_order,id'; echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL; $sql='SELECT p.slug,r.slug AS role_slug FROM permissions p LEFT JOIN permission_role pr ON pr.permission_id=p.id LEFT JOIN roles r ON r.id=pr.role_id WHERE p.slug="settings.manage"'; echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;"

Expected:
- six seeded settings
- settings.manage exists
- super_admin is attached when the role exists

ACCEPTANCE B - MISSION CONTROL PAGE
-----------------------------------
Open:

http://alasne.net.local/admin/settings

Expected:
- Platform Settings page loads
- Runtime Environment shows LOCAL
- APP_URL shows local configured URL
- General, Regional Defaults, and Administration groups render
- no secret values are displayed
- Recent Setting Changes initially may be empty
- Settings appears in the sidebar for an authorized super_admin
- Platform Settings appears in Mission Control workflow navigation

ACCEPTANCE C - VALID UPDATE + TYPED READ
----------------------------------------
In Mission Control change:

Default Admin Page Size:
25 -> 30

Save Platform Settings.

Expected:
- success message reports one setting changed
- audit history contains one admin.default_page_size entry
- operator is recorded
- value is 30

Verify typed API:

php -r "$app=require 'bootstrap/app.php'; $settings=$app->container->make('App\Services\Settings\PlatformSettingsService'); $value=$settings->get('admin.default_page_size'); var_export($value); echo PHP_EOL; echo gettype($value).PHP_EOL;"

Expected:

30
integer

Then return the value to 25 through Mission Control.

Expected:
- second audit record
- typed service returns integer 25

ACCEPTANCE D - VALIDATION IS ATOMIC
-----------------------------------
Attempt to save Default Admin Page Size as:

5

Expected:
- validation error says it must be at least 10
- no setting values change
- no new audit row is created

Also verify an invalid support email, such as:

not-an-email

Expected:
- validation error
- no partial update to any other setting from the same form submission

ACCEPTANCE E - NO-OP SAVE
-------------------------
Save the page without changing any value.

Expected:
- success message says no changes were required
- audit row count does not increase

ACCEPTANCE F - DATABASE / ENVIRONMENT BOUNDARY
----------------------------------------------
Confirm the page does not contain editable fields for:
- DB_PASSWORD
- APP_KEY
- STRIPE_SECRET_KEY
- STRIPE_WEBHOOK_SECRET
- MAIL_PASSWORD
- AI API keys
- supplier credentials

Those remain environment configuration.

PRODUCTION / TURBIFY
--------------------
Do not configure production values on Turbify during local acceptance.

After the Settings foundation and later dependent modules pass local
and stage acceptance, production deployment will:
1. deploy the tested source
2. run migration 000056 against the production database
3. configure production-safe platform values in /admin/settings
4. keep all production secrets in Turbify's protected environment

BRANCH SAFETY
-------------
Do not merge to master until explicitly requested.
