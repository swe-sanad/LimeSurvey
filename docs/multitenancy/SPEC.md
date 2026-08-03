# LimeSurvey Multi-Tenancy — Design Spec (single instance, internal org tenants)

**Target:** swe-sanad/LimeSurvey fork (LimeSurvey 7.0.7, Yii 1, PHP 8.1) · deployed at `surveys.swe.com.ly`
**Model chosen by owner:** ONE instance, tenants *inside* it (rejected: instance-per-tenant).
**Status:** design only — no implementation. This is a large, high-risk retrofit.

---

## 1. Executive summary & the honest risk

LimeSurvey is **not** multi-tenant and never was. There is no organization concept; `UserManagementController` shows *every* user to anyone holding the global "user management" permission; the admin UI, the RemoteControl API, exports, and countless raw SQL queries all assume a single global tenant. Making one instance safely host mutually-invisible tenants means **retrofitting an ownership boundary into ~20 years of code that assumes none exists.**

The core danger is not building the features (orgs, signup, teams) — those are additive and normal. The danger is **isolation leakage**: a single unscoped query, export path, or API method that lets org A read org B's data. There is no way to make that risk zero by construction in a shared-DB retrofit; it can only be driven down by (a) funnelling access through a *small number* of central chokepoints and (b) an automated isolation test suite that actively tries to break out of each tenant.

**Recommendation:** proceed in phases, and treat **Phase 0 (isolation spike)** as a go/no-go gate before investing in the rest. If the central chokepoint can't be made to hold, the honest answer is to fall back to instance-per-tenant (which is why it was the original recommendation).

---

## 2. Decisions locked (owner input) & assumptions

**Confirmed by owner:**
- **Single shared URL, own hosting** — one instance at `surveys.swe.com.ly` on the SWE VPS. (Confirms model B.)
- **Membership: one org per user** — a user belongs to exactly one org, **except the `auditor` role**, which may be granted cross-org access (§4/§5).
- **Public survey list DISABLED** — the site root will not enumerate surveys; surveys are reached only by their own link/access rules (§9).
- **Per-survey visibility** — surveys can be `draft` / `public` / `invite-only` / `private (org-internal)`; not everything is open to anyone (§9).

**Assumptions (still):** tens–low-hundreds of tenants; shared-DB row-level `owner_org_id` scoping (schema-per-tenant noted in §5 as the heavier alternative); Postgres on the shared `platform-postgres`.

---

## 3. What's tenant-owned vs global (from the real schema)

Grounded in `installer/create-database.php` + `application/models/`.

| Entity (table) | Classification | Notes |
|---|---|---|
| `surveys` (+ all child: `groups`, `questions`, `answers`, `question_attributes`, `conditions`, `assessments`, `quota*`, `defaultvalues`, `surveys_languagesettings`) | **tenant-owned** | already has `owner_id` (a user); add `owner_org_id`. Child data reachable only via its `sid`. |
| response tables `{prefix}survey_<sid>`, timing `{prefix}survey_<sid>_timings` | **tenant-owned** | dynamic per-survey tables; isolated transitively via the survey's org. |
| token tables `{prefix}tokens_<sid>` | **tenant-owned** | participants of one survey; transitive via survey. |
| `users` | **tenant-owned** | add `owner_org_id`; a user belongs to exactly one org. |
| `permissions` | **tenant-owned (derived)** | scope by the entity's org; never grant cross-org. |
| `user_groups`, `user_in_groups` | **tenant-owned** | groups are within an org. |
| `participants`, `participant_attribute*`, `participant_shares` (Central Participant DB) | **tenant-owned** | has `owner_uid`; add/derive `owner_org_id`. Sharing must stay intra-org. |
| `settings_user` | **tenant-owned** | per-user, transitively per-org. |
| `notifications`, `saved_control` (partial responses), `survey_links` | **tenant-owned** | transitive via survey/user. |
| `labelsets`, `labels` | **needs-decision** | reusable answer labels; today global. Choose: global library vs per-org. Recommend **per-org** (add `owner_org_id`) to avoid leaking custom label sets. |
| `templates`, `template_configuration` (survey themes), `question_themes` | **needs-decision** | base/installed themes = **global**; org-uploaded themes = **tenant-owned**. Split by an `owner_org_id NULL = global` convention. |
| `settings_global`, `plugins`, `plugin_settings`, `surveymenu*`, `boxes`, `question_types`, `failed_login_attempts`, `asset_version` | **global/shared** | platform-level; only the platform super-admin touches these. |

**Key existing hook:** `surveys.owner_id` and `participants.owner_uid` already express *user* ownership — org ownership layers on top (a survey's org = its owner's org, materialized as `owner_org_id` for query speed + integrity).

---

## 4. Tenant data model

- New table **`organizations`**: `org_id` (PK), `name`, `slug`, `status`, `created_at`, `plan/quota` fields, `created_by_uid`.
- **User → org:** add `users.owner_org_id` (FK). One org per user (simplest; a membership table `org_members` is the alternative if a user must belong to several orgs — **out of scope unless required**).
- **Survey → org:** add `surveys.owner_org_id`, set on create from the creator's org.
- Same `owner_org_id` column on the other top-level tenant-owned tables (participants, labelsets, org-owned templates/themes).
- **Super-admin / global rows:** `owner_org_id IS NULL` = platform-global (base themes, the platform super-admin user).
- **Auditor (the one cross-org exception):** a user still has a single home `owner_org_id`, but a user with the `auditor` role may hold **audit grants** into other orgs via a new **`org_auditor_grants`** table (`uid`, `org_id`, `granted_by`, `scope` = read/export). This is the *only* sanctioned way a user touches more than one org — and it is read/export only, never write.
- **Migration:** via LimeSurvey's own DB-version mechanism — bump `$config['dbversionnumber']`, add an update step under `application/helpers/update/updates/` that creates `organizations`, adds the columns, backfills existing rows to a default "org 1", and creates FKs/indexes. This is the sanctioned, upgrade-safe path (no ad-hoc SQL).

---

## 5. Isolation mechanism — THE make-or-break decision

Two viable strategies; a shared-DB retrofit should use **both layers** below, because neither alone is sufficient.

### Layer A — the access chokepoint (primary): make `Permission` org-aware
LimeSurvey already funnels survey access through `Permission::hasSurveyPermission($sid, $area, $crud)` and global access through `Permission::hasGlobalPermission(...)`. The audit found ~50–138 controller actions call these. **Change the chokepoint, not the call sites:**
- `hasSurveyPermission` returns **false** if the survey's `owner_org_id` ≠ the current user's org — **unless** the caller is the platform super-admin, **or** an `auditor` holding an `org_auditor_grants` row for that org (and then only `read`/`export` CRUD passes; `create`/`update`/`delete` stay denied). The auditor exception lives in this one method, so it can't be forgotten elsewhere.
- User-management access is scoped so an org-admin can only see/act on users where `owner_org_id` = their org (fixes the confirmed "shows all users" leak in `UserManagementController`).
- This single change makes every already-permission-checked path tenant-safe. **Highest leverage, smallest surface.**

### Layer B — list scoping: tenant-aware ActiveRecord default scope
For the *listing* queries (survey list, user list, participant list, label sets, themes) add `WHERE owner_org_id = <current org>`. LimeSurvey's `LSActiveRecord` (extends `CActiveRecord`) supports `defaultScope()` — add it on the tenant-owned models so `find*()` auto-filters. Resolve "current org" once per request from the session user via a small `TenantContext` helper.

### The residual risk (be honest): raw SQL & the API bypass both layers
- `defaultScope` does **not** apply to raw `createCommand()->queryAll()`, and LimeSurvey has **many** such queries (exports, statistics, integrity checks, the CGridView data providers). Each is a potential leak and must be audited/scoped in Phase 3.
- The **RemoteControl API** (`RemoteControlJsonrpc`/`Xmlrpc`) is a *whole separate access surface* — `list_surveys`, `export_responses`, `get_participant_properties`, etc. — that must be org-scoped independently. This is arguably the single biggest isolation surface after the admin UI.

### Alternative: schema-per-tenant (stronger isolation)
Instead of `owner_org_id` columns, give each org its **own Postgres schema** (`org_<id>.*`) on the shared DB, and switch `search_path` per request. Pros: near-instance-level isolation, no per-query scoping, no cross-tenant column leaks. Cons: LimeSurvey's dynamic per-survey tables + its DB layer aren't built for per-request schema switching; migrations run × N schemas; heavier. **Worth a serious look if Phase 0 shows row-level scoping is too leaky** — it moves isolation from "every query must remember" to "the connection is already scoped."

---

## 6. Self-registration (net-new — LimeSurvey has none)

Confirmed: `RegisterController` is **survey-participant** registration, not admin signup. New flow required:
- Public **`/signup`** → creates a `User` + a new `Organization`, marks the user org-admin, sends verification email. (Reuse LimeSurvey's user model + password hashing; do **not** reuse `RegisterController`.)
- Optionally invite-based join to an existing org (token email → join as member).
- Guard against abuse (email verification, rate-limit, CAPTCHA — LimeSurvey ships one).

## 7. Team management
Org-admin uses the (now org-scoped) user management to invite/create members; new users inherit the admin's `owner_org_id`; org-admin gets superadmin-equivalent rights **within their org only** (Layer A enforces the boundary).

## 8. Super-admin (platform)
A platform role (`owner_org_id IS NULL` + a `is_platform_admin` flag) that bypasses the org scope — the only role that sees all orgs, manages `settings_global`/plugins/base themes, and does cross-org support. Org-admins are explicitly *not* this.

## 9. Survey-list, per-survey visibility, and public-taking isolation

- **Public survey list DISABLED (owner decision).** The site root (`surveys/index`) no longer enumerates surveys — that listing showed *every* org's active surveys and is a cross-tenant leak. Surveys are reached only by their own URL + the access rules below. (The root becomes a neutral landing / login, not a survey directory.)
- **Per-survey visibility — new `surveys.visibility` enum:**
  - **`draft`** — not activated; being built, collects no responses. *(= LimeSurvey's inactive state.)*
  - **`public`** — activated, open-access; anyone with the link responds. *(= open-access mode.)*
  - **`invite-only`** — activated, closed-access; only invited participants (tokens) respond. *(= closed-access/token mode — reuse LimeSurvey's participants/tokens.)*
  - **`private` (org-internal)** — only authenticated **members of the survey's org** may view/respond. **This is the one genuinely new access mode**: it needs a gate on the survey-taking entry that requires the respondent to be a logged-in member of the survey's `owner_org_id`.
  - `draft`/`public`/`invite-only` map onto existing LimeSurvey survey states; only `private` is net-new. A small state machine + the `visibility` column ties them together and drives the survey-taking gate.
- Survey IDs are global integers (enumerable), but a guessed `sid` only reaches a *public* answer form; `draft`/`invite`/`private` reject unauthorized respondents, and no admin data leaks. Documented, low severity.

## 10. Upgrade / divergence strategy
This becomes a **permanent hard fork** — upstream LimeSurvey merges become manual. Mitigate by keeping tenant logic **centralized**: `organizations` model, `TenantContext` helper, the `Permission` chokepoint edits, the signup controller, and the `defaultScope`s — a handful of files — rather than scattering `owner_org_id` checks across hundreds of call sites. Every touch point goes in a `MULTITENANCY.md` change-map.

---

## 11. Phasing (each phase is independently spec-able & shippable)

- **Phase 0 — Isolation spike (go/no-go).** `organizations` table + `owner_org_id` on `surveys`+`users` + the Layer-A `Permission` org-gate + ONE automated isolation test (org A cannot open org B's survey by direct `sid` URL). Prove the chokepoint holds before building more. *If it can't be made airtight, stop and reconsider instance-per-tenant.*
- **Phase 1 — MVP.** Add self-signup (create org+admin), org-scoped survey list + user list (Layer B), super-admin role. Result: real orgs, isolated surveys + users, self-service.
- **Phase 2 — Breadth.** Scope participants (CPDB) + label sets + org themes + `settings_user`; team invites; org-admin console; resolve the public-survey-list leak (§9).
- **Phase 3 — Hardening.** The full isolation **test suite** across *every* route (admin UI, exports, statistics, **RemoteControl API**, public); audit all raw-SQL paths; per-org quotas/limits; plugin isolation review.

## 12. Verification — how we PROVE isolation (the linchpin)
An automated suite that, logged in as **org A**, attempts to read/enumerate **org B**'s data by every route and asserts denial:
- Admin: survey list, open survey by `sid`, edit, export responses, statistics, participant DB, user list.
- **API:** RemoteControl `list_surveys`, `export_responses`, `get_summary`, `get_participant_properties` with org-B ids.
- Public: the root survey list must not reveal org-B surveys.
This suite is the real deliverable of the whole project — features are easy, *proven* isolation is the hard, valuable part. (Runs in the Docker test env from the audit's `tests/README.md`.)

## 13. Risks & open questions (honest list)
1. **Raw SQL / `createCommand` queries** bypass AR scoping — count + audit needed (Phase 3).
2. **RemoteControl API** is a full parallel access surface — must be org-scoped explicitly.
3. **Public survey-list leak** — RESOLVED: root listing disabled; per-survey visibility (`draft`/`public`/`invite`/`private`) governs access (§9). New sub-risk: the `private` (org-internal) survey-taking gate is net-new code and must itself be isolation-tested.
4. **Admin UI global assumptions** — menus, dashboards, quick-stats assume global counts; many views need org-awareness.
5. **Plugins** can query the DB directly, bypassing everything — third-party plugins are an isolation hole; restrict which plugins org-admins may enable.
6. **Global survey IDs** enumerable (low severity — only public forms).
7. **Upgrade divergence** — permanent hard fork; upstream security patches become manual merges.
8. **Performance** — an `owner_org_id` filter + index on every hot table; validate query plans.
9. **Membership cardinality** — RESOLVED: one org per user; the `auditor` role is the single cross-org exception (read/export grants via `org_auditor_grants`, §4/§5).

---

## 14. Recommendation
Do **Phase 0 first as a spike** and let it decide. It's a few days of work that de-risks the entire (multi-month) project by testing the one thing that can sink it — the isolation chokepoint. If Phase 0 holds, Phase 1 delivers a real MVP; if it doesn't, we've spent days, not months, learning that instance-per-tenant is the right call after all.
