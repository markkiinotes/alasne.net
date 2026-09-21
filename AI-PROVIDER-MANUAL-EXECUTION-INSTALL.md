AI PROVIDER + MANUAL EXECUTION
==============================

PURPOSE
-------
Add the first live AI execution path to Alasne while preserving strict
operator control.

This phase adds:
- OpenAI Responses API provider integration
- explicit manual execution only
- CSRF + ai.manage authorization
- metadata-only AI run audit history
- provider/model/token/latency/error observability
- no autonomous actions
- no tools
- no scheduled AI runs
- no persistent prompt/response content

BRANCH
------
feature/ai-provider-manual-execution

Base checkpoint:
63864d04b7d7434413033514a2442d7a7d57dc29
Close AI Engine foundation acceptance

OPENAI API CONTRACT
-------------------
Current OpenAI documentation recommends the Responses API for new text
generation applications.

Endpoint:
POST https://api.openai.com/v1/responses

Authentication:
Authorization: Bearer <server-side credential>

Alasne sends:
- model
- instructions
- input
- max_output_tokens
- store = false
- non-sensitive Alasne run metadata

The client walks all response output items and collects content items
whose type is output_text. It does not assume output[0] is the assistant
message because Responses output may contain reasoning/tool items.

MODEL
-----
No model is hard-coded in application source.

Platform setting:
ai.model

Suggested first local smoke-test value:
gpt-5.6-terra

This can be changed later without changing code.

MIGRATION
---------
000058_create_ai_runs.php

Creates:
ai_runs

The table stores metadata only:
- agent ID
- operator user ID
- provider
- model
- run status
- SHA-256 hash of prompt
- prompt character length
- output character length
- OpenAI response ID
- OpenAI request ID
- provider response status
- input/output/total token usage
- latency
- error code/message
- timestamps

The table does NOT store:
- API keys
- prompt text
- response text

PROVIDER ARCHITECTURE
---------------------
Contract:
App\Contracts\AI\AiProvider

Provider resolver:
App\Services\AI\AiProviderResolver

OpenAI implementation:
App\Services\AI\Providers\OpenAiResponsesClient

Manual execution orchestration:
App\Services\AI\AiExecutionService

RUN GUARDRAILS
--------------
A manual run is rejected unless:
- AI Engine is enabled
- Manual Execution Only remains enabled
- selected agent exists
- agent status is draft or active
- agent includes manual_prompting capability
- provider is configured
- model is configured
- API-key environment-variable name is valid
- credential exists in that environment variable
- prompt is not blank
- prompt is no more than 12,000 characters

The initial draft agent may be manually tested. Draft status does NOT
authorize scheduled, autonomous, or tool-enabled execution.

MISSION CONTROL
---------------
GET /admin/ai
POST /admin/ai/agents/{agent_id}/run

Both require:
- authenticated session
- ai.manage

The manual response is shown from the operator's session after the run.
It is removed from flash/session presentation after the page is read and
is never inserted into ai_runs.

OPENAI CREDENTIAL
-----------------
The credential remains in the local .env during XAMPP testing.

The expected environment-variable name is:

OPENAI_API_KEY

Do NOT put the API key in Platform Settings.
Do NOT commit the API key.
Do NOT paste the API key into chat, screenshots, command output, GitHub,
or acceptance documentation.

LOCAL CODE GATE
---------------
From:

C:\xampp\htdocs\alasne.net

run:

git fetch origin
git switch --track origin/feature/ai-provider-manual-execution

php -l database\migrations\000058_create_ai_runs.php
php -l app\Contracts\AI\AiProvider.php
php -l app\Repositories\AiRunRepository.php
php -l app\Services\AI\AiProviderResolver.php
php -l app\Services\AI\Providers\OpenAiResponsesClient.php
php -l app\Services\AI\AiExecutionService.php
php -l app\Services\AI\AiEngineService.php
php -l app\Controllers\Admin\AiEngineController.php
php -l app\Views\admin\ai\index.php
php -l config\routes.php

composer dump-autoload -o
git diff --check
php alasne migrate

powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-stage.ps1

Expected:
- all ten PHP lint checks pass
- Composer optimized autoload succeeds
- git diff --check prints nothing
- migration 000058 completes
- Stage build verification PASSED

ACCEPTANCE A - LOCKED WITHOUT CONFIGURATION
-------------------------------------------
Before adding a credential or enabling AI, open:

http://alasne.net.local/admin/ai

Expected:
- Manual Execution = Locked
- Configuration Readiness = Not Ready
- prompt textarea is disabled
- Run Manual Test button is disabled
- Recent AI Runs is empty

This proves simply deploying the provider code does not activate AI.

ACCEPTANCE B - LOCAL CREDENTIAL BOUNDARY
----------------------------------------
Add the API key only to:

C:\xampp\htdocs\alasne.net\.env

using:

OPENAI_API_KEY=<your key>

Do not share the value.

Restart Apache if needed so the application reloads environment values.

Open /admin/ai.

Expected before enabling AI/model:
- Credential Presence = Configured
- the key VALUE is never displayed
- Manual Execution remains Locked until the remaining settings are ready

ACCEPTANCE C - ENABLE MANUAL TEST CONFIGURATION
-----------------------------------------------
Open:

http://alasne.net.local/admin/settings

In AI Engine:
- AI Engine Enabled = checked
- AI Provider = OpenAI
- Default AI Model = gpt-5.6-terra
- API Key Environment Variable = OPENAI_API_KEY
- Manual Execution Only = checked

Save.

Open /admin/ai.

Expected:
- AI Engine = Enabled
- Provider = openai
- Default Model = gpt-5.6-terra
- Credential Presence = Configured
- Manual-Only Guardrail = Required
- Configuration Readiness = Ready
- Manual Execution = Available

ACCEPTANCE D - FIRST LIVE MANUAL RUN
------------------------------------
In the Alasne Operations Assistant manual test form, submit:

Reply with exactly this sentence: Alasne AI manual execution is working.

Expected:
- request is explicitly initiated by the logged-in operator
- success message shows a run ID
- response is displayed on the page
- Recent AI Runs contains a succeeded row
- provider = openai
- model = gpt-5.6-terra
- token usage is populated when returned by OpenAI
- latency is populated
- OpenAI response/request IDs are recorded when returned
- no autonomous action occurs

The response text is displayed but is not persisted in ai_runs.

ACCEPTANCE E - DATABASE CONTENT BOUNDARY
----------------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); $s=$pdo->query('SELECT id,agent_id,requested_by_user_id,provider,model,status,prompt_sha256,prompt_length,output_length,response_id,provider_request_id,provider_status,input_tokens,output_tokens,total_tokens,latency_ms,error_code,error_message,created_at,completed_at FROM ai_runs ORDER BY id DESC LIMIT 5'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;"

Expected:
- successful run metadata is present
- prompt_sha256 is present
- prompt_length is present
- output_length is present
- no prompt_text column exists
- no response_text column exists
- no API-key column exists

ACCEPTANCE F - DISABLE GUARD
----------------------------
In Platform Settings:
- uncheck AI Engine Enabled
- keep all other values unchanged

Open /admin/ai.

Expected:
- Manual Execution returns to Locked
- Configuration Readiness = Not Ready
- form is disabled
- existing run history remains visible

Return AI Engine Enabled to checked only if further live development is
continuing immediately; otherwise leave it disabled between test sessions.

ACCEPTANCE G - FAILURE AUDIT
----------------------------
For a controlled failure test, temporarily set Default AI Model to a
clearly invalid model identifier such as:

alasne-invalid-model-test

Keep AI enabled and submit a manual prompt.

Expected:
- request fails with a provider error
- ai_runs records status = failed
- latency and sanitized error message are recorded
- no prompt/response text is stored
- API key is never exposed

Then restore Default AI Model to:
gpt-5.6-terra

No autonomous behavior is introduced by this test.

SECURITY BOUNDARY
-----------------
This phase does NOT implement:
- tool calling
- web search
- file search
- database mutation by the model
- supplier actions
- order actions
- customer messaging
- scheduled runs
- agent loops
- background AI execution
- public/customer AI access

Those capabilities require separate guardrail and authorization phases.

BRANCH SAFETY
-------------
Do not merge to master until explicitly requested.


ACCEPTANCE RESULTS - 2026-09-20
-------------------------------
Branch + migration checkpoint: PASSED

Observed:
- local branch = feature/ai-provider-manual-execution
- migration 000058_create_ai_runs.php completed successfully

Acceptance A - LOCKED WITHOUT CONFIGURATION: PASSED

Observed on /admin/ai:
- Manual execution is locked
- Manual Agent Test workspace is visible
- prompt textarea is disabled
- Run Manual Test button is disabled
- Recent AI Runs contains no rows
- operator notice correctly explains readiness requirements

This proves deploying the live-provider/manual-execution code does not
activate AI by itself.


Local code/staging gate: PASSED

Observed:
- all ten AI provider/manual execution PHP lint checks passed
- Composer optimized autoload generated successfully with 725 classes
- git diff --check returned no output
- migration 000058_create_ai_runs.php had already completed successfully
- staging source synchronization completed with 0 failed files
- staging Composer/platform requirements passed
- source-tree parity passed
- Stage build verification PASSED
- staging .env, vendor, and storage were preserved


Acceptance B - LOCAL CREDENTIAL BOUNDARY: PASSED

Observed:
- .env is ignored by Git via .gitignore
- PHP runtime reports OPENAI_API_KEY configured
- /admin/ai shows Credential Presence = Configured
- API key value is not displayed
- AI Engine remains Disabled
- Default Model remains Not selected
- Configuration Readiness remains Not Ready
- Manual Execution remains Locked

This proves the provider credential can be loaded from the local XAMPP
environment without storing or exposing the secret in Platform Settings.
