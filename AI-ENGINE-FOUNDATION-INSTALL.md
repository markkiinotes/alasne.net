AI ENGINE FOUNDATION
====================

PURPOSE
-------
Create the secure Mission Control foundation for Alasne's AI Engine
without enabling outbound AI execution yet.

This phase intentionally separates:
1. configuration
2. agent definitions
3. credential presence/readiness
from actual provider calls.

No AI provider request can be made by this foundation.

BRANCH
------
feature/ai-engine-foundation

Base checkpoint:
339082020fded03906b3d9952d6868929451915b
Close platform settings foundation acceptance

DEPENDENCY
----------
Migration 000056_create_platform_settings.php must already be applied.

MIGRATION
---------
000057_create_ai_engine_foundation.php

Creates:
- ai_agents
- ai_agent_versions

Seeds/upserts permission:
- ai.manage

The permission is attached to super_admin when that role exists.

Seeds Platform Settings:
- ai.enabled = false
- ai.provider = openai
- ai.model = blank
- ai.api_key_env = OPENAI_API_KEY
- ai.manual_execution_only = true

The API key setting stores only the environment-variable NAME.
It never stores the API credential itself.

INITIAL AGENT
-------------
Alasne Operations Assistant

Status:
draft

Initial capabilities:
- manual_prompting
- read_only_analysis

The agent cannot execute in this phase.

MISSION CONTROL
---------------
New route:

GET /admin/ai

Requires:
- authenticated session
- ai.manage

The existing admin sidebar already contains an AI Engine link behind
ai.manage. Migration 000057 makes that dormant link functional.

Mission Control Product Intelligence also includes:
AI Engine

AI ENGINE DASHBOARD
-------------------
The page displays:
- AI enabled/disabled state
- configured provider
- selected/default model
- API-key environment-variable name
- credential presence as Configured/Missing
- manual-only guardrail state
- configuration readiness
- execution availability
- agent counts
- seeded agent definitions
- capabilities
- version count

The page never displays an API key value.

READINESS RULE
--------------
Configuration readiness requires:
- ai.enabled = true
- provider selected
- model selected
- API-key environment variable named
- API-key environment variable has a non-empty value
- manual_execution_only = true

Even if configuration readiness becomes true, execution_available remains
false in this foundation phase.

There is no POST /admin/ai/execute route and no provider client.

LOCAL INSTALL / CODE GATE
-------------------------
From:

C:\xampp\htdocs\alasne.net

run:

git fetch origin
git switch --track origin/feature/ai-engine-foundation

php -l database\migrations\000057_create_ai_engine_foundation.php
php -l app\Repositories\AiAgentRepository.php
php -l app\Services\AI\AiEngineService.php
php -l app\Controllers\Admin\AiEngineController.php
php -l app\Views\admin\ai\index.php
php -l app\Services\Admin\MissionControlNavigationService.php
php -l config\routes.php

composer dump-autoload -o
git diff --check
php alasne migrate

powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-stage.ps1

Expected:
- all seven PHP lint checks pass
- Composer optimized autoload succeeds
- git diff --check prints nothing
- migration 000057 completes
- Stage build verification PASSED

ACCEPTANCE A - SCHEMA + PERMISSION + SETTINGS
----------------------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); echo 'AI SETTINGS'.PHP_EOL; $s=$pdo->query('SELECT setting_key,value_type,value_text FROM platform_settings WHERE group_key="ai" ORDER BY sort_order,id'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL; echo 'PERMISSION'.PHP_EOL; $s=$pdo->query('SELECT p.slug,r.slug AS role_slug FROM permissions p LEFT JOIN permission_role pr ON pr.permission_id=p.id LEFT JOIN roles r ON r.id=pr.role_id WHERE p.slug="ai.manage"'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL; echo 'AGENTS'.PHP_EOL; $s=$pdo->query('SELECT id,name,slug,status,model_override,max_output_tokens,capabilities_json FROM ai_agents ORDER BY id'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;"

Expected:
- five AI settings
- ai.enabled = 0
- ai.provider = openai
- ai.model blank
- ai.api_key_env = OPENAI_API_KEY
- ai.manual_execution_only = 1
- ai.manage exists and is assigned to super_admin
- one draft Alasne Operations Assistant exists

ACCEPTANCE B - AI ENGINE PAGE
-----------------------------
Open:

http://alasne.net.local/admin/ai

Expected:
- page loads under ai.manage
- sidebar AI Engine link is active
- Total Agents = 1
- Active Agents = 0
- Draft Agents = 1
- AI Engine = Disabled
- Provider = openai
- Default Model = Not selected
- Credential Variable = OPENAI_API_KEY
- Credential Presence = Missing unless local .env already defines it
- Manual-Only Guardrail = Required
- Configuration Readiness = Not Ready
- Execution = Foundation Only
- seeded Alasne Operations Assistant is visible

ACCEPTANCE C - SETTINGS INTEGRATION
-----------------------------------
Open:

http://alasne.net.local/admin/settings

Expected:
- a new AI Engine group appears
- the five AI settings are editable through the existing typed
  Platform Settings system
- API Key Environment Variable displays OPENAI_API_KEY, not a secret
- no API key value is displayed

Do NOT enter an API key into Platform Settings.

Do NOT enable AI Engine yet.

Do NOT choose a model yet.

ACCEPTANCE D - NO EXECUTION SURFACE
-----------------------------------
Confirm:
- /admin/ai contains no prompt execution form
- there is no Run/Execute Agent button
- there is no route that sends a prompt to an AI provider
- Execution card says Foundation Only

This phase must make zero outbound AI requests.

NEXT PHASE
----------
After this foundation passes:
AI Provider + Manual Execution

That phase will:
- verify current provider API requirements
- add a provider abstraction/client
- keep the API key in the protected environment
- add explicit operator-initiated execution only
- record run metadata/audit history
- preserve manual-only guardrails
- add provider error handling and usage telemetry

No autonomous actions will be enabled at that stage.

BRANCH SAFETY
-------------
Do not merge to master until explicitly requested.
