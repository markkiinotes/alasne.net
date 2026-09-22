AI SCOPED OPERATIONAL INTELLIGENCE — FOUNDATION GATE
======================================================

BRANCH
------
feature/ai-scoped-operational-intelligence

Base: 623fdd00a3b315b50b4a5a6071460ff939324029
Previous accepted phase: AI Read-Only Operational Context

FIRST SLICE
-----------
App\Services\AI\AiScopedOperationalContextService

This first slice is an isolated, read-only service. It does NOT yet
alter the existing preview route or the manual AI execution flow.
There is no migration and no provider request.

Scope constraints:
- authenticated operator ID required
- currently restricted to the platform super_admin role
- explicit positive store ID required; there is no all-store option
- store must exist
- dates must be valid YYYY-MM-DD with start <= end
- date span may not exceed 366 days
- agent must be Draft or Active and have operational_snapshot capability
- every fixed query binds store_id
- order/return queries bind the date range
- low-stock list is store-scoped, but represents current inventory
  rather than a historical reporting-period snapshot
- only fixed fields are returned; no customer name/contact/address
- no model-created SQL, data modification, or provider call
- context length is capped at 18,000 characters

Because the existing KPI report has some global-query fallbacks,
this service intentionally does not reuse its summary helpers.
The existing global operational_snapshot capability remains unchanged
until scoped preview and execution are explicitly integrated and tested.

LOCAL CODE GATE
---------------
From C:\xampp\htdocs\alasne.net:

git fetch origin
git switch --track origin/feature/ai-scoped-operational-intelligence

php -l app\Services\AI\AiScopedOperationalContextService.php
composer dump-autoload -o
git diff --check
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-stage.ps1

Expected: lint clean, no diff-check output, staging build PASSED.

ACCEPTANCE A — VALID SCOPED PREVIEW (NO PROVIDER CALL)
------------------------------------------------------
The following reads only store 1 and does not print the actual JSON:

php -r "$app=require 'bootstrap/app.php'; $s=$app->container->make('App\Services\AI\AiScopedOperationalContextService'); $p=$s->previewForOperator(1,1,1,'2026-09-01','2026-09-22'); echo 'TYPE: '.$p['context_type'].PHP_EOL.'STORE ID: '.$p['store_id'].PHP_EOL.'PERIOD: '.$p['date_from'].' to '.$p['date_to'].PHP_EOL.'LENGTH: '.$p['length'].PHP_EOL.'HASH LENGTH: '.strlen($p['sha256']).PHP_EOL.'SECTIONS: '.implode(', ',array_keys($p['snapshot'])).PHP_EOL;"

Expected: store ID 1, valid period, length >0 and <=18000,
hash length 64, only scope/summary/returns/recent_orders/low_stock_products.

ACCEPTANCE B — FAIL-CLOSED GUARDS
----------------------------------
Run this safely without printing any snapshot or secret:

php -r "$app=require 'bootstrap/app.php'; $s=$app->container->make('App\Services\AI\AiScopedOperationalContextService'); foreach([['unauthenticated',1,0,1,'2026-09-01','2026-09-22'],['all-stores',1,1,0,'2026-09-01','2026-09-22'],['missing-store',1,1,999999,'2026-09-01','2026-09-22'],['invalid-date',1,1,1,'2026-09-99','2026-09-22'],['reversed-period',1,1,1,'2026-09-22','2026-09-01'],['archived-agent',2,1,1,'2026-09-01','2026-09-22']] as $t){try{$s->previewForOperator($t[1],$t[2],$t[3],$t[4],$t[5]); echo $t[0].': UNEXPECTEDLY ALLOWED'.PHP_EOL;}catch(Throwable $e){echo $t[0].': BLOCKED'.PHP_EOL;}}"

All six cases should be BLOCKED.

ACCEPTANCE C — NO AI EXECUTION
------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); echo 'AI ENABLED: '.$pdo->query('SELECT value_text FROM platform_settings WHERE setting_key=\"ai.enabled\"')->fetchColumn().PHP_EOL; echo 'RUN COUNT: '.$pdo->query('SELECT COUNT(*) FROM ai_runs')->fetchColumn().PHP_EOL;"

Expected AI ENABLED: 0 and RUN COUNT: 6.

NEXT IMPLEMENTATION SLICES
--------------------------
1. Introduce explicit operator-to-store authorization for non-super-admin
   accounts; no inference from an arbitrary store ID or UI visibility.
2. Integrate scoped preview and manual run selectors, with scope checked
   again server-side at execution time.
3. Audit store ID and reporting dates as metadata; retain no context body.
4. Multi-store cross-contamination acceptance with at least two stores,
   an unauthorized account, and store-specific data.
5. Make sure all-store/global snapshot access is restricted explicitly
   or removed from the narrower operator path.
6. Run one authorized scoped manual analysis, disable AI again, and
   verify no unauthorized provider calls.

DO NOT MERGE TO MASTER without explicit request.


LOCAL FOUNDATION GATE — PASSED (2026-09-22)
------------------------------------------
Operator verified:
- fetched and switched to feature/ai-scoped-operational-intelligence
- AiScopedOperationalContextService.php: no PHP syntax errors
- optimized Composer autoload: 729 classes
- git diff --check: no output
- staging synchronization: 0 failed files, 0 mismatches
- staging Composer dependencies and platform requirements: passed
- source-tree parity: passed
- Stage build verification PASSED
- staging .env, vendor, and storage preserved

No migration or provider call was required for this foundation gate.
Acceptance A-C remain pending local runtime verification.


ACCEPTANCE A — VALID SCOPED SNAPSHOT: PASSED
--------------------------------------------
Runtime preview for agent 1, operator 1, store 1:
- type = mission_control_store_snapshot_v1
- store_id = 1
- reporting period = 2026-09-01 through 2026-09-22
- context length = 2557 characters (under 18000)
- SHA-256 length = 64
- sections = schema, scope, summary, returns, recent_orders,
  low_stock_products

ACCEPTANCE B — FAIL-CLOSED GUARDS: PASSED
-----------------------------------------
All six probes were blocked:
- unauthenticated operator
- all-stores / store ID 0
- nonexistent store
- invalid date
- reversed date range
- archived agent

ACCEPTANCE C — NO AI EXECUTION: PASSED
--------------------------------------
ai.enabled = 0
AI RUN COUNT = 6
The isolated scoped service did not create provider runs.

The first scoped foundation is accepted. UI and provider execution
integration remain pending. No master merge was performed.


ACCEPTANCE RESULTS — FOUNDATION
-------------------------------
Acceptance A — VALID SCOPED PREVIEW: PASSED

Observed:
- context type = mission_control_store_snapshot_v1
- store_id = 1
- reporting period = 2026-09-01 through 2026-09-22
- context length = 2557 characters
- SHA-256 length = 64
- sections:
  schema
  scope
  summary
  returns
  recent_orders
  low_stock_products

Acceptance B — FAIL-CLOSED GUARDS: PASSED

Blocked as required:
- unauthenticated operator
- all-stores / store_id 0
- missing store
- invalid date
- reversed reporting period
- archived agent

No invalid request was unexpectedly allowed.

Acceptance C — NO AI EXECUTION: PASSED

Verified:
- ai.enabled = 0
- AI RUN COUNT = 6

The scoped foundation produced no provider request and did not modify
the accepted AI execution state.
