# LimeSurvey Multi-Tenancy — Phased Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Turn the single-tenant LimeSurvey fork into a single-instance, multi-tenant SaaS where users self-register, own an organization, add a team, and build surveys invisible to other orgs.

**Architecture:** Shared database with a row-level `owner_org_id` tenant boundary. Isolation is enforced at ONE central chokepoint (`Permission::hasSurveyPermission`/global access) plus ActiveRecord default-scopes for listing; an auditor role is the only cross-org exception. Proven, not assumed, by an isolation test suite. Full design: `limesurvey-multitenancy-spec.md`.

**Tech Stack:** LimeSurvey 7.0.7, Yii 1, PHP 8.1, PostgreSQL (shared `platform-postgres`), PHPUnit 9.6, the fork's Dockerized test env (`tests/README.md`).

## Global Constraints

- PHP target **8.1** (project platform pin); tooling (psalm/phpunit) runs only on 8.1 — use the Docker test env, not the local PHP 8.4.
- Schema changes go **only** through LimeSurvey's DB-version update mechanism (`application/config/version.php` `dbversionnumber` + a block in `application/helpers/update/updatedb_helper.php`) — never ad-hoc SQL. Every step is reversible/reviewable.
- All tenant logic stays **centralized** (a handful of files: `Organization` model, `TenantContext`, the `Permission` chokepoint, the signup controller, the model `defaultScope`s) to survive future upstream merges. Log every touch point in a new `MULTITENANCY.md` change-map.
- Auditor cross-org access is **read/export only**, enforced inside the one chokepoint method.
- No commit may leave the isolation test suite red. Commit style: Conventional Commits, no Claude attribution (repo rule).

---

## File Structure (created / modified)

**New:**
- `application/models/Organization.php` — the tenant entity (LSActiveRecord).
- `application/models/OrgAuditorGrant.php` — auditor cross-org grants.
- `application/core/TenantContext.php` — resolves "current org" for the request (a Yii application component).
- `application/controllers/SignupController.php` — public account+org self-registration (Phase 1).
- `application/models/traits/TenantOwnedTrait.php` — `defaultScope` + `owner_org_id` behavior shared by tenant-owned models (Phase 1).
- `tests/unit/multitenancy/IsolationTest.php` — the go/no-go + growing isolation suite.
- `MULTITENANCY.md` — the change-map / operator doc.

**Modified:**
- `application/config/version.php` — bump `dbversionnumber`.
- `application/helpers/update/updatedb_helper.php` — the migration block(s).
- `application/models/Permission.php` — the org-aware chokepoint (`hasSurveyPermission`, global user access).
- `application/models/Survey.php`, `User.php` — `owner_org_id`, relations, scope.
- `application/controllers/UserManagementController.php` — org-scope the user list/actions (fix the confirmed leak) [Phase 1].

---

# PHASE 0 — Isolation Spike (GO / NO-GO GATE)

**Purpose:** prove the central chokepoint holds before investing further. Exit criterion at the bottom decides whether the whole shared-instance approach continues.

### Task 0.1: Stand up the test env + org fixtures

**Files:**
- Test: `tests/unit/multitenancy/IsolationTest.php` (create, skeleton)
- Reference: `tests/bootstrap.php`, `tests/TestBaseClass.php`, `tests/README.md`

**Interfaces:**
- Produces: `IsolationTest` base with a `setUp()` that creates two orgs + one user + one survey each. Later isolation tests extend/reuse these fixtures.

- [ ] **Step 1:** Bring up the Dockerized test env per `tests/README.md` (PHP 8.1 container + Postgres, `touch enabletests`, `installfromconfig`). Confirm `./vendor/bin/phpunit --testsuite unit` runs a trivial existing test green. *(This is the environment the audit documented; it is a prerequisite for everything below.)*
- [ ] **Step 2:** Create `tests/unit/multitenancy/IsolationTest.php` extending `TestBaseClass` with an empty `testPlaceholder()` asserting `true`. Run it green to confirm wiring.
- [ ] **Step 3:** Commit: `test(multitenancy): scaffold isolation test harness`.

### Task 0.2: `organizations` table via the update mechanism

**Files:**
- Modify: `application/config/version.php` (bump `dbversionnumber`)
- Modify: `application/helpers/update/updatedb_helper.php` (new version block)
- Test: `tests/unit/multitenancy/IsolationTest.php`

**Interfaces:**
- Produces: table `{prefix}organizations(org_id PK, name, slug UNIQUE, status, created_by_uid, created_at)`.

- [ ] **Step 1: Failing test** — assert the table exists:
```php
public function testOrganizationsTableExists()
{
    $schema = Yii::app()->db->schema->getTable('{{organizations}}', true);
    $this->assertNotNull($schema, 'organizations table missing');
    $this->assertArrayHasKey('org_id', $schema->columns);
}
```
- [ ] **Step 2:** Run it → FAIL (table missing).
- [ ] **Step 3:** Bump `dbversionnumber` in `version.php` (current 709 → next free, e.g. 710). In `updatedb_helper.php` add a guarded block that runs when `$iOldDBVersion < 710`, creating `organizations` with Yii's `createTable` (portable across MySQL/Postgres), then updates the stored dbversion. Follow the exact shape of an existing block in that file.
- [ ] **Step 4:** Re-run the migration in the test DB (drop/reinstall or trigger the updater), run the test → PASS.
- [ ] **Step 5:** Commit: `feat(multitenancy): add organizations table (db 710)`.

### Task 0.3: `owner_org_id` on `surveys` + `users` (+ backfill)

**Files:**
- Modify: `updatedb_helper.php` (extend the 710 block or add 711), `version.php`
- Modify: `application/models/Survey.php`, `application/models/User.php`
- Test: `IsolationTest.php`

**Interfaces:**
- Produces: nullable `owner_org_id` (int) on `{prefix}surveys` and `{prefix}users`, indexed; existing rows backfilled to a seeded default org (`org_id = 1`, "Default"). `Survey->owner_org_id`, `User->owner_org_id` readable via AR.

- [ ] **Step 1: Failing test:**
```php
public function testSurveyAndUserHaveOwnerOrg()
{
    $s = Yii::app()->db->schema->getTable('{{surveys}}', true);
    $u = Yii::app()->db->schema->getTable('{{users}}', true);
    $this->assertArrayHasKey('owner_org_id', $s->columns);
    $this->assertArrayHasKey('owner_org_id', $u->columns);
}
```
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** In the migration: `addColumn` `owner_org_id` to `surveys` + `users`; create an index on each; insert a "Default" org (org_id 1) if none; `UPDATE ... SET owner_org_id = 1 WHERE owner_org_id IS NULL`. (Survey/User models auto-map new columns — no model code needed yet beyond a relation, added in 0.5.)
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit: `feat(multitenancy): add owner_org_id to surveys + users, backfill default org`.

### Task 0.4: `org_auditor_grants` table + model

**Files:**
- Modify: `updatedb_helper.php`, `version.php`
- Create: `application/models/OrgAuditorGrant.php`
- Test: `IsolationTest.php`

**Interfaces:**
- Produces: `{prefix}org_auditor_grants(id PK, uid, org_id, granted_by, scope)`; `OrgAuditorGrant::hasGrant($uid, $orgId): bool`.

- [ ] **Step 1: Failing test:**
```php
public function testAuditorGrantModel()
{
    $g = new OrgAuditorGrant();
    $g->uid = 1; $g->org_id = 2; $g->granted_by = 1; $g->scope = 'read';
    $this->assertTrue($g->save(), print_r($g->errors, true));
    $this->assertTrue(OrgAuditorGrant::hasGrant(1, 2));
    $this->assertFalse(OrgAuditorGrant::hasGrant(1, 3));
}
```
- [ ] **Step 2:** Run → FAIL (no table/model).
- [ ] **Step 3:** Add the table in the migration; create `OrgAuditorGrant` (LSActiveRecord) with `tableName()`, `rules()`, and a static `hasGrant($uid,$orgId)` querying by `uid`+`org_id`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit: `feat(multitenancy): add org_auditor_grants table + model`.

### Task 0.5: `TenantContext` + `Organization` model + Survey→org relation

**Files:**
- Create: `application/models/Organization.php`, `application/core/TenantContext.php`
- Modify: `application/config/internal.php` (register `TenantContext` as an app component), `Survey.php`/`User.php` (relation to Organization)
- Test: `IsolationTest.php`

**Interfaces:**
- Produces: `TenantContext::currentOrgId(): ?int` (the logged-in user's `owner_org_id`, or null for a guest / platform super-admin); `Organization` AR.

- [ ] **Step 1: Failing test** — with a fixtured user in org 2 set as the session user, `TenantContext::currentOrgId()` returns 2:
```php
public function testTenantContextResolvesCurrentOrg()
{
    $this->loginAsFixtureUserInOrg(2); // helper sets Yii user session to a uid whose owner_org_id=2
    $this->assertSame(2, TenantContext::currentOrgId());
}
```
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement `Organization` (AR). Implement `TenantContext::currentOrgId()`: read the current user id (the same source `Permission` uses — `Permission::getUserId()` / `Yii::app()->session['loginID']`), load the `User`, return `owner_org_id`. Register it in `internal.php` components. Add `Survey->owner_org_id` relation.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit: `feat(multitenancy): Organization model + TenantContext resolver`.

### Task 0.6: The org-aware `Permission` chokepoint (the whole point)

**Files:**
- Modify: `application/models/Permission.php` (`hasSurveyPermission`)
- Test: `IsolationTest.php`

**Interfaces:**
- Consumes: `TenantContext::currentOrgId()`, `OrgAuditorGrant::hasGrant()`, `Survey->owner_org_id`.
- Produces: `hasSurveyPermission($sid,$area,$crud,$uid=null)` that is org-aware (see rules).

- [ ] **Step 1: Failing test** — same-org allowed, cross-org denied, auditor read allowed but write denied:
```php
public function testSurveyPermissionIsOrgScoped()
{
    // fixtures: userA(org1) owns surveyA(org1); userB(org2) owns surveyB(org2)
    $this->loginAsFixtureUserInOrg(1);
    $this->assertTrue(Permission::model()->hasSurveyPermission($this->surveyA, 'survey', 'read'));
    $this->assertFalse(Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'read'));
    // auditor of org2 (read grant): read yes, update no
    $this->loginAsAuditorWithGrant($this->auditorUid, 2);
    $this->assertTrue(Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'read'));
    $this->assertFalse(Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'update'));
}
```
- [ ] **Step 2:** Run → FAIL (current impl ignores org).
- [ ] **Step 3:** At the TOP of `hasSurveyPermission`, before the existing logic: load the survey's `owner_org_id`; let `$curOrg = TenantContext::currentOrgId()`. Allow only if platform super-admin, OR `owner_org_id === $curOrg`, OR (`$crud` in `['read','export']` AND `OrgAuditorGrant::hasGrant($uid, owner_org_id)`). Otherwise `return false` immediately. Then fall through to the existing per-survey permission logic. *(This is a pre-filter — it can only ever REMOVE access, never grant, so it cannot weaken existing checks.)*
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit: `feat(multitenancy): org-scope hasSurveyPermission (+ auditor read/export exception)`.

### Task 0.7: GO/NO-GO — end-to-end isolation via a real request path

**Files:**
- Test: `tests/functional/multitenancy/CrossTenantSurveyAccessTest.php`

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Failing test** — as userA(org1), hit the actual admin survey-view action for surveyB(org2) and assert a 403 (not the survey), exercising a controller that calls the chokepoint (e.g. `SurveyAdministrationController` view):
```php
public function testCannotOpenAnotherOrgsSurvey()
{
    $this->loginAsFixtureUserInOrg(1);
    $resp = $this->getAdminAction('surveyAdministration/view', ['surveyid' => $this->surveyB]);
    $this->assertHttpStatus(403, $resp);
}
```
- [ ] **Step 2:** Run → observe result. If it already denies via 0.6, good; if the controller reaches data via a path that bypasses `hasSurveyPermission`, that is exactly the finding this gate exists to surface.
- [ ] **Step 3:** Make it pass through the chokepoint (the controller should already call `hasSurveyPermission`; if a specific action doesn't, that's a documented bypass to add to the Phase-3 raw-SQL/bypass audit — note it in `MULTITENANCY.md`).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit: `test(multitenancy): cross-tenant survey access denied end-to-end`.

### ✅ Phase 0 EXIT CRITERIA (the go/no-go)
- All isolation tests green: same-org allowed, cross-org denied at both the model AND a real controller path; auditor read/export-only works.
- A written tally in `MULTITENANCY.md` of any controller/API path found to reach survey data WITHOUT going through the chokepoint. **If that tally is small and closable → GO (proceed to Phase 1). If it is large/uncloseable → NO-GO: recommend instance-per-tenant instead.** This decision is the entire deliverable of Phase 0.

---

# PHASE 1 — MVP (coarse; own detailed plan when reached)

**Deliverable:** users self-register → get an org → see only their org's surveys + users → build surveys. Isolation enforced.

**Task groups:**
1. **Self-signup** — `SignupController` (`/signup`): create `User` + new `Organization`, set the user org-admin, email verification, rate-limit/CAPTCHA. New surveys/users inherit the creator's `owner_org_id` (set in the create paths).
2. **List scoping (Layer B)** — `TenantOwnedTrait` with a `defaultScope` (`owner_org_id = TenantContext::currentOrgId()`) applied to `Survey`, `User`; the admin survey list + `UserManagementController` list now show only the org (closes the confirmed "shows all users" leak).
3. **Super-admin role** — `owner_org_id IS NULL` + platform-admin flag bypasses the scope + chokepoint; org-admins are superadmin *within* their org only.
4. **Per-survey visibility schema** — `surveys.visibility` enum + admin UI control; wire `draft`→inactive and `invite-only`→closed-access onto LimeSurvey's existing states (`private` gate deferred to Phase 2).

**Exit criteria:** a fresh signup yields an isolated org whose admin can create a team user + a survey and cannot see any other org's surveys/users through the admin UI; the Phase-0 isolation suite still green + extended to cover the user list.

# PHASE 2 — Breadth (coarse)

**Deliverable:** the rest of the tenant-owned surface is scoped; teams + the new `private` survey mode work.

**Task groups:** scope Participants/CPDB (`participants`, `participant_shares`), label sets, org-owned themes/templates, `settings_user` (extend `TenantOwnedTrait` to each) · team invites (invite-token join) · the **`private` (org-internal) survey-taking gate** (require a logged-in org member to take) · auditor console (grant/list) + org-admin console.

**Exit criteria:** isolation suite extended to participants/labels/themes and all four visibility modes; auditor read/export across a granted org works and write is denied.

# PHASE 3 — Hardening (coarse)

**Deliverable:** provable, production-grade isolation.

**Task groups:** the **RemoteControl API** org-scoped (`list_surveys`, `export_responses`, `get_participant_properties`, …) — its own isolation tests · audit + scope every raw-SQL/`createCommand` path that reads tenant data (grep-driven inventory) · admin-UI global-count/dashboard fixes · plugin isolation policy (which plugins org-admins may enable) · per-org quotas/limits · performance pass (indexes + query plans on `owner_org_id`).

**Exit criteria:** the full isolation suite covers every route (UI, exports, statistics, RemoteControl API, public survey-taking) and is green; the raw-SQL bypass inventory is empty or explicitly justified.

---

## Self-Review (against the spec)

- **Spec coverage:** org model (§4)→0.2–0.5; isolation chokepoint (§5 Layer A)→0.6; list scoping (§5 Layer B)→P1.2; auditor (§4/§5)→0.4/0.6; self-reg (§6)→P1.1; team (§7)→P2; super-admin (§8)→P1.3; visibility + public-list-off (§9)→P1.4/P2; migration (§4)→0.2–0.4; upgrade centralization (§10)→Global Constraints + `MULTITENANCY.md`; phasing (§11)→this doc; verification (§12)→0.7 + P3; risks (§13: raw SQL, API, plugins)→P3. **No uncovered spec section.**
- **Placeholder scan:** Phase 0 steps carry real code/commands; Phases 1–3 are deliberately coarse task-groups (each gets its own detailed plan when reached — the sanctioned decomposition for a multi-subsystem effort), not hidden placeholders.
- **Type consistency:** `TenantContext::currentOrgId()`, `OrgAuditorGrant::hasGrant($uid,$orgId)`, `Survey->owner_org_id`, `Permission::hasSurveyPermission($sid,$area,$crud,$uid)` used consistently across tasks.
