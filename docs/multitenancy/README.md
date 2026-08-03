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

## Bypass inventory
Populate during the test-env run + the Phase 3 audit — from `git grep`-ing raw SQL / `createCommand` reads of survey data, and from Task 0.7's findings.

_(none catalogued yet)_
