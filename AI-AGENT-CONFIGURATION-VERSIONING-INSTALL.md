AI AGENT CONFIGURATION + VERSIONING
====================================

PURPOSE
-------
Add controlled Mission Control administration for AI agent definitions
while preserving the security boundaries accepted in the previous AI
phases.

This phase adds:
- create AI agents as Draft
- edit agent definitions
- immutable version snapshots
- explicit activate / return-to-draft / archive / restore transitions
- stable slugs
- required change notes
- capability allowlisting
- no destructive delete
- no autonomous execution
- no new provider or tool capability

BRANCH
------
feature/ai-agent-configuration-versioning

Base checkpoint:
2c32e2829fcb26f90f77a495938743b397531692
Close AI provider manual execution acceptance

MIGRATION
---------
000059_version_ai_agent_definitions.php

The existing ai_agent_versions table is extended with:
- slug
- status

The migration backfills version #1 for any existing AI agent that does
not yet have a version snapshot.

Existing version history is never deleted by migration rollback.

VERSIONING RULES
----------------
Every new agent:
- receives a stable generated slug
- starts as Draft
- records immutable version #1

Every definition change:
- updates the current ai_agents row
- records the next immutable ai_agent_versions snapshot
- requires a change note
- records the operator user ID when available

Every status transition:
- records the next immutable version
- requires a change note

Saving a definition without any actual definition change:
- does not create a new version

Version snapshots include:
- name
- slug
- description
- system instructions
- status
- model override
- max output tokens
- capabilities
- operator
- change note
- timestamp

AGENT SLUGS
-----------
A slug is generated during agent creation.

Examples:
Alasne Acceptance Analyst
-> alasne-acceptance-analyst

If the slug already exists, a numeric suffix is added.

Slugs are intentionally not editable after creation so future references
remain stable.

APPROVED CAPABILITIES
---------------------
This phase continues to allow only:
- manual_prompting
- read_only_analysis

No tool, mutation, supplier, customer messaging, scheduling, autonomous,
or external-action capability is added.

ACTIVATION RULES
----------------
An agent can be activated only when:
- system instructions are present
- manual_prompting capability is present

An active agent cannot be edited into a definition that violates those
activation rules. It must first be returned to Draft.

Archived agents:
- remain in the database
- keep all version history
- cannot be manually executed
- must return to Draft before they can be activated again

There is no Delete Agent operation.

MISSION CONTROL ROUTES
----------------------
GET  /admin/ai/agents/create
POST /admin/ai/agents
GET  /admin/ai/agents/{agent_id}/edit
POST /admin/ai/agents/{agent_id}
GET  /admin/ai/agents/{agent_id}/versions
POST /admin/ai/agents/{agent_id}/status

All require:
- authenticated session
- ai.manage

The main /admin/ai dashboard adds:
- Create Agent
- Edit
- Versions

LOCAL CODE GATE
---------------
From:

C:\xampp\htdocs\alasne.net

run:

git fetch origin
git switch --track origin/feature/ai-agent-configuration-versioning

php -l database\migrations\000059_version_ai_agent_definitions.php
php -l app\Repositories\AiAgentRepository.php
php -l app\Services\AI\AiAgentManagementService.php
php -l app\Controllers\Admin\AiAgentController.php
php -l app\Views\admin\ai\agents\form.php
php -l app\Views\admin\ai\agents\versions.php
php -l app\Views\admin\ai\index.php
php -l config\routes.php

composer dump-autoload -o
git diff --check
php alasne migrate

powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-stage.ps1

Expected:
- all eight PHP lint checks pass
- Composer optimized autoload succeeds
- git diff --check prints nothing
- migration 000059 completes
- Stage build verification PASSED

ACCEPTANCE A - VERSION SCHEMA + BACKFILL
----------------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); echo 'VERSION COLUMNS'.PHP_EOL; $s=$pdo->query('SHOW COLUMNS FROM ai_agent_versions WHERE Field IN ("slug","status")'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL; echo 'EXISTING AGENT'.PHP_EOL; $s=$pdo->query('SELECT a.id,a.name,a.slug,a.status,COUNT(v.id) AS version_count,MAX(v.version_number) AS latest_version FROM ai_agents a LEFT JOIN ai_agent_versions v ON v.agent_id=a.id WHERE a.slug="alasne-operations-assistant" GROUP BY a.id,a.name,a.slug,a.status'); echo json_encode($s->fetch(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL; echo 'INITIAL VERSION'.PHP_EOL; $s=$pdo->query('SELECT version_number,name,slug,status,change_note FROM ai_agent_versions WHERE agent_id=1 ORDER BY version_number'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;"

Expected:
- slug column exists
- status column exists
- Alasne Operations Assistant has version_count = 1
- latest_version = 1
- initial version note = Initial agent snapshot
- initial snapshot slug/status match current agent

ACCEPTANCE B - MANAGEMENT UI
----------------------------
Open:

http://alasne.net.local/admin/ai

Expected:
- Create Agent button is visible
- existing Operations Assistant shows version count 1
- each agent row has Edit and Versions controls
- global AI Engine remains Disabled
- Manual Execution remains Locked

Open the Operations Assistant Versions page.

Expected:
- version #1 is visible
- change note = Initial agent snapshot
- full system instructions are available only inside the expandable
  snapshot details
- history is read-only

ACCEPTANCE C - CREATE A DRAFT TEST AGENT
----------------------------------------
Use Create Agent and enter:

Agent Name:
Alasne Acceptance Analyst

Description:
Temporary read-only agent used to verify Alasne agent versioning.

System Instructions:
Assist authorized Alasne operators with read-only analysis during agent
configuration acceptance testing. Do not modify platform data or take
external actions.

Model Override:
leave blank

Maximum Output Tokens:
600

Approved Capabilities:
- manual_prompting
- read_only_analysis

Create the agent.

Expected:
- agent is created as Draft
- slug = alasne-acceptance-analyst
- version count = 1
- version #1 note = Created agent
- created_by/updated_by identify the current operator

ACCEPTANCE D - EDIT + NO-OP VERSION CONTROL
-------------------------------------------
Edit the test agent.

Change Description to:

Temporary read-only agent used to verify Alasne immutable agent versioning.

Change Maximum Output Tokens:
600 -> 700

Change Note:
Acceptance definition edit

Save.

Expected:
- version #2 is created
- slug remains alasne-acceptance-analyst
- version #1 remains unchanged
- current definition has output limit 700

Then save the exact same definition again with Change Note:

Acceptance no-op save

Expected:
- UI reports no definition changes were required
- version count remains 2
- no version #3 is created from the no-op save

ACCEPTANCE E - ACTIVATE + ACTIVE GUARD
--------------------------------------
On the test agent edit page, Activate with change note:

Acceptance activation

Expected:
- status becomes Active
- version #3 is created
- version #3 status = active

While Active, edit the definition and temporarily uncheck:

manual_prompting

Use change note:

This change should be blocked

Attempt to save.

Expected:
- update is rejected
- message explains manual_prompting is required before activation
- current agent remains Active
- manual_prompting remains present in the persisted definition
- version count remains 3

ACCEPTANCE F - ARCHIVE WITHOUT DELETE
-------------------------------------
Archive the test agent with change note:

Acceptance archive

Expected:
- status becomes Archived
- version #4 is created
- agent remains visible in Mission Control
- Edit and Versions remain available
- manual execution for the archived agent is unavailable
- no row is deleted

ACCEPTANCE G - IMMUTABLE HISTORY
--------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); $s=$pdo->query('SELECT id,name,slug,status,max_output_tokens,capabilities_json FROM ai_agents WHERE slug="alasne-acceptance-analyst"'); $agent=$s->fetch(PDO::FETCH_ASSOC); echo 'CURRENT AGENT'.PHP_EOL.json_encode($agent,JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL; if(!$agent){exit(1);} $id=(int)$agent['id']; $s=$pdo->prepare('SELECT version_number,name,slug,status,max_output_tokens,capabilities_json,change_note,changed_by_user_id,created_at FROM ai_agent_versions WHERE agent_id=? ORDER BY version_number'); $s->execute([$id]); echo 'VERSIONS'.PHP_EOL.json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL;"

Expected four versions:
1. Draft / 600 tokens / Created agent
2. Draft / 700 tokens / Acceptance definition edit
3. Active / 700 tokens / Acceptance activation
4. Archived / 700 tokens / Acceptance archive

The slug must be identical across all four versions.

ACCEPTANCE H - GLOBAL AI SAFETY STATE
-------------------------------------
Run:

php -r "$app=require 'bootstrap/app.php'; $pdo=$app->container->make('PDO'); $s=$pdo->query('SELECT setting_key,value_text FROM platform_settings WHERE setting_key IN ("ai.enabled","ai.manual_execution_only") ORDER BY setting_key'); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT).PHP_EOL; echo 'AI RUN COUNT: '.$pdo->query('SELECT COUNT(*) FROM ai_runs')->fetchColumn().PHP_EOL;"

Expected:
- ai.enabled = 0
- ai.manual_execution_only = 1
- AI RUN COUNT remains 5

Agent configuration/versioning must not enable the AI Engine and must not
create any provider run.

PHASE BOUNDARY
--------------
This phase does NOT add:
- tools
- database actions performed by the model
- scheduled execution
- autonomous loops
- customer-facing AI
- supplier actions
- order actions
- file/web retrieval
- delete-agent behavior

BRANCH SAFETY
-------------
Do not merge to master until explicitly requested.


ACCEPTANCE RESULTS - 2026-09-20
-------------------------------
Local code/staging gate: PASSED

Observed:
- local branch switched to feature/ai-agent-configuration-versioning
- all eight PHP lint checks passed
- Composer optimized autoload completed with 727 classes
- git diff --check returned no output
- migration 000059_version_ai_agent_definitions.php completed successfully
- staging synchronization completed without failed files
- staging Composer/platform requirements passed
- source-tree parity passed
- Stage build verification PASSED
- staging .env, vendor, and storage were preserved

Acceptance A - VERSION SCHEMA + BACKFILL: PASSED

Observed:
- ai_agent_versions.slug exists as varchar(150)
- ai_agent_versions.status exists as varchar(30)
- Alasne Operations Assistant remains agent id 1
- current slug = alasne-operations-assistant
- current status = draft
- version_count = 1
- latest_version = 1
- immutable version #1 matches the current slug/status
- version #1 change note = Initial agent snapshot
