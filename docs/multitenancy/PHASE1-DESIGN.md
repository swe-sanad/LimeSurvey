# LimeSurvey Multi-Tenancy — Phase 1 Design (Full)

> Approved design for Phase 1. Builds on the Phase-0 foundation (db 710: `organizations`,
> `org_auditor_grants`, `owner_org_id` on `surveys`/`users`, `TenantContext`, and the org-aware
> `Permission::hasSurveyPermission` chokepoint). See [`SPEC.md`](SPEC.md) · [`PLAN.md`](PLAN.md) · [`README.md`](README.md).

**Product decisions (locked):** neutral **"Surveys"** brand · **focused** marketing landing page ·
signup is **instant activation + CAPTCHA** (no email verification) · **Full Phase 1** scope.

## Goal

Visit `/` → understand the platform → sign up → get your own isolated organization → build surveys
no other org can see. Plus per-survey visibility modes and team invites.

## Grounding (from the code, not assumed)

- Root `/` currently → `SurveysController::actionPublicList()` → `layout_survey_list.twig`. The
  `show_survey_list` flag does **not** gate it; replacing root = **route `/` to a new controller**
  (also satisfies the "disable public survey list" requirement).
- New public pages: clone the **installer** pattern — a `CController` + PHP view + minimal layout +
  `registerPackage('bootstrap')` (Bootstrap 5 ships globally). No survey-theme/Twig-sandbox machinery.
- Phase-0 gaps: `Survey.php` has only the `organization` relation; `User.php` has **no** org code
  (only the migrated column). List-scoping + org-stamping are greenfield.
- Superadmin predicate to reuse everywhere: `Permission::model()->hasGlobalPermission('superadmin','read',$uid)`
  (folds in `forcedsuperadmin`). Survey-list filtering already centralizes in
  `Survey::getPermissionCriteria()` → add the org clause there (count-safe), not a fragile `defaultScope`.
- User creation path: `User::insertUser()` (+ `setPassword()` = bcrypt). Admin list:
  `UserManagementController::actionIndex()` → `User::search()`.
- CAPTCHA: reuse LimeSurvey's built-in Yii `CCaptcha` action (GD is present). No new dependency.

## Isolation invariant (the load-bearing rule)

**Org-admins never receive the global `superadmin` permission.** That flag nulls their org in
`TenantContext` and would bypass tenancy. An org-admin gets a *curated* global permission set
(surveys / users / labels / templates / settings CRUD) that the tenant layer (chokepoint + list
scoping) confines to their own org. Only the platform operator (uid 1 / `forcedsuperadmin` / DB
`superadmin`) sees across orgs.

`Survey::beforeSave` and `User::beforeSave` stamp `owner_org_id` from the creator's org when unset,
so new surveys and team members inherit the org automatically through the existing create paths.

---

## Workflow A — Front door (landing + signup + isolation core)

**New:**
- `application/controllers/LandingController.php` + `application/views/landing/index.php` +
  `application/views/layouts/landing.php` (installer-pattern; neutral "Surveys" brand; hero + value
  prop + 3–4 feature cards + how-it-works + signup CTA + footer; responsive; WCAG AA; strings via `gT()`).
- `application/controllers/SignupController.php` + `application/views/signup/index.php`: form (org name,
  full name, email, password + confirm, CAPTCHA). POST → validate → create `Organization` → create
  `User` (`owner_org_id` = new org, active) → grant org-admin permission set → auto-login → redirect
  to `/admin`. Rate-limit + CAPTCHA against abuse.
- `application/models/services/OrgSignup.php` (or a thin model method) — the transactional
  create-org-then-user-then-grant unit, so the controller stays thin and the logic is unit-testable.

**Modified:**
- Route `/` → `landing/index`, `/signup` → `signup/index` (`application/config/routes.php` /
  `internal.php` defaultController).
- `application/models/Survey.php` — `beforeSave` org-stamp; org clause in `getPermissionCriteria()`.
- `application/models/User.php` — `owner_org_id` relation to `Organization`; `beforeSave` org-stamp;
  org filter in `search()`; both superadmin-bypassed.
- `application/controllers/UserManagementController.php` — confirm the list now scopes (closes the
  "shows all users" leak).

**Tests (`tests/unit/multitenancy/`):** signup creates an isolated org + active org-admin (no
`superadmin`); two orgs cannot see each other's surveys **or** users; superadmin sees all; a new
survey/user created by an org-admin inherits `owner_org_id`.

**Exit:** a fresh signup yields an isolated org whose admin creates a survey + a user and sees no
other org's data; Phase-0 suite still green + extended to the user list.

## Workflow B — Per-survey visibility

**Migration → db 711** (`Update_711.php` + `create-database.php` parity + `version.php` bump):
`surveys.visibility` enum `draft` / `public` / `invite` / `private` (default `draft`), indexed.

Admin UI control (survey settings) to set it. Wire onto existing survey states at the taking gate:
`draft`→inactive/closed, `invite`→token-required, `public`→`listpublic`, `private`→require a
logged-in **org member** to take. Public survey list (if surfaced) shows only `public`.

**Tests:** each mode gates survey-taking correctly; `private` denies a non-org visitor.

## Workflow C — Team invites

Org-admin invites by email → `org_invites` token (single-use, expiring) → invitee sets password →
joins the inviter's org as a **normal org user** (not org-admin, no `superadmin`), `owner_org_id` =
inviter's org. Org-admin manages the team list (list org users, deactivate).

**Tests:** invited user lands in the correct org, sees only org surveys/users, cannot self-escalate;
expired/used token rejected.

---

## Build discipline

`/build` per workflow: branch `feat/phase1-tenancy` (off `feat/multitenancy`) → each agent loads
`code-quality` + `testing-discipline` → `qa-expert` APPROVED gate + `code-reviewer` (architecture +
quality/testing lenses) → micro-commits (Conventional Commits, **no Claude attribution**) → PR →
deploy the image to `surveys.swe.com.ly` so the landing + signup are live. No commit leaves the
isolation suite red.

## Deliberate simplifications (ponytail)

- No email verification (instant activation chosen); welcome email skipped for MVP.
- CAPTCHA = LimeSurvey's built-in Yii captcha, no new dep.
- Org clause added in `getPermissionCriteria()` (survey) rather than a `defaultScope` — count-safe,
  reuses the existing central filter.
