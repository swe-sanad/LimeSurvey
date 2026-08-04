# LimeSurvey Multi-Tenancy — Change Map & Status

Single-instance, organization-based multi-tenancy for this fork.
**Design:** [`SPEC.md`](SPEC.md) · **Plan:** [`PLAN.md`](PLAN.md)

## Centralized touch points (keep tenant logic HERE, to survive upstream merges)

| File | Responsibility |
|------|----------------|
| `application/models/Organization.php` | The tenant entity. |
| `application/models/OrgAuditorGrant.php` | The one cross-org exception (auditor read/export grants). |
| `application/core/TenantContext.php` | Resolves the current request's org (mirrors Permission's uid source). |
| `application/models/Permission.php` → `hasSurveyPermission` / `isOrgAllowedForSurvey` | **THE isolation chokepoint** — a subtractive pre-filter that can only deny, never grant. |
| `application/models/Survey.php` | `organization` relation (+ `owner_org_id` column). |
| `application/helpers/update/updates/Update_710.php` | Schema migration (db version 710). |

## Status

### Phase 0 — foundation (this commit set)
Built (PLAN Tasks 0.2–0.6): `organizations` + `org_auditor_grants` tables; `owner_org_id` on `surveys`/`users` (backfilled to the seeded Default org, `org_id` 1); `TenantContext`; the org-aware `hasSurveyPermission` chokepoint with the auditor read/export exception; `tests/unit/multitenancy/IsolationTest.php`.

- ✅ `php -l` clean on all touched files.
- ✅ Chokepoint reviewed: subtractive-only (cannot weaken existing checks); mirrors LimeSurvey's own uid/superadmin resolution; auditor limited to `read`/`export`.
- ✅ Fixed during review: the migration seeded the Default org with an explicit `org_id = 1`, which would leave Postgres' serial sequence at 0 and collide when the second org is created — now inserts without an explicit id so the sequence advances.
- ✅ **RUNTIME-VERIFIED GREEN.** Ran the isolation suite in a throwaway PHP-8.1 + Postgres container built from this branch: `OK (4 tests, 11 assertions)` — same-org allow, **cross-org DENY**, auditor read-yes / write-no. `installfromconfig` also exercised the fresh-install schema (via the fixed `create-database.php`).
- 🔧 **Bugs the runtime run surfaced + fixed:** (1) Postgres seed-sequence collision (`org_id=1`); (2) fresh-install schema gap (`create-database.php`); (3) survey-sid fixture (sid is not auto-increment); (4) the auditor exception (a subtractive-only gate never actually *granted* read) plus org-from-session-vs-uid plus a stale per-uid cache — chokepoint refactored to a 3-way `DENY / GRANT / NORMAL` decision that resolves the org from the checked uid.
- ⬜ **Task 0.7** (end-to-end controller `403` on a cross-org `sid`) still to write — the model + permission-chokepoint layer is proven; the HTTP-layer test is the remaining Phase-0 nicety.

### Phase 0 GO / NO-GO — **GO** ✅
Isolation is proven at the model + permission-chokepoint layer: any route through `hasSurveyPermission` denies cross-org access. **Proceed to Phase 1.** Still recommended before Phase 1 code lands: the raw-SQL / bypass inventory (grep for `createCommand` reads of survey data that skip the chokepoint) and Task 0.7's HTTP-layer test.

### Phase 1 — Workflow A (front door) — DEPLOYED to production
Delivered (branch `feat/phase1-tenancy`): org-scoped survey + user lists (org clause in `Survey::getPermissionCriteria()` and `User::search()`, superadmin-bypassed via the null-org path); `owner_org_id` auto-stamp in `Survey`/`User` `beforeSave`; public self-signup (`SignupController` + `OrgSignup` service, instant activation + math CAPTCHA, transactional org+user+grant with rollback); a neutral-"Surveys" marketing landing page at `/` (`LandingController`, installer-pattern) that also disables the public survey list at root.

- ✅ QA gate: **APPROVED** — isolation traced through the real code paths (cross-org read denied; superadmin bypass is a clause-omission, never a leaky `= NULL`); no BLOCKERs. All touched PHP `php -l` clean.
- ✅ Org-admin grant routed through `Permission::setPermissions(..., $bBypassCheck=true)`, which structurally strips `superadmin` — an org-admin can never receive it.
- 🔧 Review fold-ins applied: auto-login now checks `authenticate()`'s result (an IP-lockout no longer dead-ends on the dashboard); signup layout carries `dir` for RTL; `OrgSignup::validate()` enforces org/full-name max lengths; signup `aria-describedby` emitted only when the error div exists; landing test asserts the real signup URL. The "duplicate-email race" is a non-issue: `users_name` has a UNIQUE DB index (`idx1_users`), same-email signups derive the same username, so the second insert fails closed and rolls back.
- ✅ **RUNTIME-VERIFIED GREEN.** Isolation + signup suites ran green in the VPS PHP-8.1 container: 15 tests passing.
- ✅ **DEPLOYED to production** (`https://surveys.swe.com.ly`) as image `limesurvey-swe:phase1`. Live DB migrated `709 → 710` (adds `organizations` + `org_auditor_grants`, `owner_org_id` columns, backfills the Default org). The neutral "Surveys" marketing landing page is live at the site root. See [`DEPLOY.md`](DEPLOY.md) for the exact deploy sequence and the config-permissions gotcha hit during rollout.
- 🔧 **`/signup` 500 — root-caused + fixed.** The signup layout's `<html dir>` calls `getLanguageRTL()`, defined by the `surveytranslator` helper; `LandingController` loaded it in `init()` but `SignupController` did not, so the layout fataled (`Call to undefined function getLanguageRTL()`). Fixed by loading the helper in `SignupController::init()`. Verified at runtime in a throwaway PHP-8.1 container (form renders, HTTP 200). Also surfaced during rollout: the host `config.php` must be group-readable by the container's `www-data` (GID 33) or the whole site fatals — see [`DEPLOY.md`](DEPLOY.md). Ships in the next image redeploy.
  - **Lesson:** a phpunit controller-render test does **not** catch the helper gap — the test bootstrap pre-loads `surveytranslator` globally, so the render succeeds there regardless (that is why `LandingControllerTest` was green while live signup broke). Controller/layout helper-loading needs an HTTP-level smoke test (in `DEPLOY.md`).

### Deferred hardening (tracked, not blocking Workflow A)
- **TenantContext fail-open (Phase-0 latent):** `currentOrgId()` returns null (= unrestricted) for a *logged-in non-superadmin whose `owner_org_id` is null*. Not reachable through Phase-1 flows — signup and org-admin user-create both stamp an org, and `Update_710` backfilled every existing user to org 1 — but a superadmin manually creating an org-less user would produce a cross-org-visible account. Fix: fail closed (scope-to-nothing) for a logged-in non-superadmin with no org, or enforce a non-null `owner_org_id` for all non-superadmins. Deferred because it touches the runtime-proven Phase-0 predicate and needs its own test cycle.
- **Per-record user IDOR:** only the user *list* is org-scoped (`User::search`); the user edit/view-by-uid actions in `UserManagementController` are not yet org-checked, so a direct id could load another org's user record. Surveys are already covered (the `hasSurveyPermission` chokepoint gates per-record). Close in Workflow C (team management) with a per-record org check on the user actions.

## Bypass inventory
Populate during the test-env run + the Phase 3 audit — from `git grep`-ing raw SQL / `createCommand` reads of survey data, and from Task 0.7's findings.

- `UserManagementController` per-record user reads (edit/view by uid) — not org-scoped yet (see Deferred hardening); close in Workflow C.
- (survey raw-SQL / `createCommand` audit still pending — Phase 3.)
