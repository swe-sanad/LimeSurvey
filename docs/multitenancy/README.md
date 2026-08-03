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
- ⚠️ **NOT runtime-verified.** `Update_710` and `IsolationTest` have **not been run** — they need the Dockerized PHP-8.1 + DB test env (`tests/README.md`, PLAN Task 0.1); the dev box is PHP 8.4 with no Docker daemon. **Running the migration + the isolation suite is the first action when that env is available.**
- ⬜ **Task 0.7** (end-to-end controller `403` on a cross-org `sid`) not yet written — needs the functional HTTP test harness.

### Phase 0 GO / NO-GO — OPEN
The go/no-go decision (PLAN "Phase 0 EXIT CRITERIA") is **pending**: it requires the isolation suite green in the test env **and** a tally of any survey-data path that bypasses the chokepoint. **Do not start Phase 1 until both hold.**

## Bypass inventory
Populate during the test-env run + the Phase 3 audit — from `git grep`-ing raw SQL / `createCommand` reads of survey data, and from Task 0.7's findings.

_(none catalogued yet)_
