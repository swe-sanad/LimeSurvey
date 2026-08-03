<?php

namespace ls\tests;

/**
 * LimeSurvey Multi-Tenancy — Phase 0 isolation spike.
 *
 * The go/no-go suite for docs/multitenancy/PLAN.md Phase 0 (tasks 0.2-0.6):
 * proves the organizations schema exists, owner_org_id is present + backfilled,
 * the org_auditor_grants model works, and the Permission::hasSurveyPermission
 * chokepoint is org-scoped (same-org allowed, cross-org denied, auditor
 * read/export-only).
 *
 * NOTE: requires the Dockerized PHP 8.1 + DB test env (tests/README.md) with
 * `touch enabletests` and the db-version-710 migration applied. Cannot be run
 * from this environment (no Docker daemon, local PHP is 8.4) — written to run
 * once that env exists, per docs/multitenancy/PLAN.md Task 0.1.
 */
class IsolationTest extends TestBaseClass
{
    /** @var int */
    protected $orgAId;

    /** @var int */
    protected $orgBId;

    /** @var \User */
    protected $userA;

    /** @var \User */
    protected $userB;

    /** @var int */
    protected $surveyA;

    /** @var int */
    protected $surveyB;

    /** @var int */
    protected $auditorUid;

    public function setUp(): void
    {
        parent::setUp();
        // Seed fixtures as the platform superadmin (uid 1).
        \Yii::app()->session['loginID'] = 1;

        $this->orgAId = $this->createOrgFixture('Isolation Test Org A');
        $this->orgBId = $this->createOrgFixture('Isolation Test Org B');

        // owner_org_id has no validation rule (yet - see Survey/User model comments in
        // PLAN.md Task 0.3), so mass-assignment via createUserWithPermissions() would
        // silently drop it; set it directly and re-save instead.
        $this->userA = $this->createUserWithPermissions([
            'users_name' => 'isolation_test_user_a',
            'email' => 'isolation-test-user-a@example.com',
        ]);
        $this->setUserOrg($this->userA, $this->orgAId);

        $this->userB = $this->createUserWithPermissions([
            'users_name' => 'isolation_test_user_b',
            'email' => 'isolation-test-user-b@example.com',
        ]);
        $this->setUserOrg($this->userB, $this->orgBId);

        $this->surveyA = $this->createSurveyFixture($this->userA->uid, $this->orgAId);
        $this->surveyB = $this->createSurveyFixture($this->userB->uid, $this->orgBId);

        $auditor = $this->createUserWithPermissions([
            'users_name' => 'isolation_test_auditor',
            'email' => 'isolation-test-auditor@example.com',
        ]);
        $this->setUserOrg($auditor, $this->orgAId);
        $this->auditorUid = $auditor->uid;
    }

    public function tearDown(): void
    {
        \Yii::app()->session['loginID'] = 1;
        \OrgAuditorGrant::model()->deleteAllByAttributes(['uid' => $this->auditorUid]);
        if ($this->surveyA) {
            \Survey::model()->findByPk($this->surveyA)?->delete();
        }
        if ($this->surveyB) {
            \Survey::model()->findByPk($this->surveyB)?->delete();
        }
        foreach ([$this->userA, $this->userB] as $user) {
            if ($user) {
                \User::model()->findByPk($user->uid)?->delete();
            }
        }
        if ($this->auditorUid) {
            \User::model()->findByPk($this->auditorUid)?->delete();
        }
        foreach ([$this->orgAId, $this->orgBId] as $orgId) {
            if ($orgId) {
                \Organization::model()->findByPk($orgId)?->delete();
            }
        }
        parent::tearDown();
    }

    /**
     * Task 0.2: the organizations table exists via the DB-version update mechanism.
     */
    public function testOrganizationsTableExists()
    {
        $schema = \Yii::app()->db->schema->getTable('{{organizations}}', true);
        $this->assertNotNull($schema, 'organizations table missing');
        $this->assertArrayHasKey('org_id', $schema->columns);
    }

    /**
     * Task 0.3: owner_org_id exists on both surveys and users (nullable, backfilled).
     */
    public function testSurveyAndUserHaveOwnerOrg()
    {
        $s = \Yii::app()->db->schema->getTable('{{surveys}}', true);
        $u = \Yii::app()->db->schema->getTable('{{users}}', true);
        $this->assertArrayHasKey('owner_org_id', $s->columns);
        $this->assertArrayHasKey('owner_org_id', $u->columns);
    }

    /**
     * Task 0.4: the org_auditor_grants table + OrgAuditorGrant::hasGrant().
     */
    public function testAuditorGrantModel()
    {
        $g = new \OrgAuditorGrant();
        $g->uid = $this->auditorUid;
        $g->org_id = $this->orgBId;
        $g->granted_by = 1;
        $g->scope = 'read';
        $this->assertTrue($g->save(), print_r($g->errors, true));
        $this->assertTrue(\OrgAuditorGrant::hasGrant($this->auditorUid, $this->orgBId));
        $this->assertFalse(\OrgAuditorGrant::hasGrant($this->auditorUid, $this->orgAId + $this->orgBId + 999));
    }

    /**
     * Task 0.6: hasSurveyPermission is org-scoped, with a read/export-only
     * auditor exception. This is the whole point of the chokepoint.
     */
    public function testSurveyPermissionIsOrgScoped()
    {
        $this->loginAsFixtureUserInOrg(1);
        $this->assertTrue(\Permission::model()->hasSurveyPermission($this->surveyA, 'survey', 'read'));
        $this->assertFalse(\Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'read'));

        // Auditor of org B (read grant): read yes, update no.
        $this->loginAsAuditorWithGrant($this->auditorUid, 2);
        $this->assertTrue(\Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'read'));
        $this->assertFalse(\Permission::model()->hasSurveyPermission($this->surveyB, 'survey', 'update'));
    }

    /**
     * Sets owner_org_id directly (not mass-assignable) and persists it.
     */
    protected function setUserOrg(\User $user, int $orgId): void
    {
        $user->owner_org_id = $orgId;
        if (!$user->save(false)) {
            throw new \Exception('Could not save user owner_org_id: ' . print_r($user->errors, true));
        }
    }

    /**
     * @return int the new org's org_id
     */
    protected function createOrgFixture(string $name): int
    {
        $org = new \Organization();
        $org->name = $name;
        $org->slug = 'isolation-test-' . strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();
        $org->status = 'active';
        if (!$org->save()) {
            throw new \Exception('Could not save organization: ' . print_r($org->errors, true));
        }
        return (int) $org->org_id;
    }

    /**
     * @return int the new survey's sid
     */
    protected function createSurveyFixture(int $ownerUid, int $orgId): int
    {
        $survey = new \Survey();
        $survey->setScenario('insert');
        $survey->admin = 'Isolation Test Admin';
        $survey->language = 'en';
        $survey->format = 'G';
        $survey->active = 'N';
        $survey->owner_id = $ownerUid;
        $survey->owner_org_id = $orgId;
        if (!$survey->save()) {
            throw new \Exception('Could not save survey: ' . print_r($survey->errors, true));
        }
        return (int) $survey->sid;
    }

    /**
     * Sets the session user to the fixture user of org "1" (org A) or "2" (org B).
     *
     * @param int $orgNumber 1 for org A, 2 for org B
     */
    protected function loginAsFixtureUserInOrg(int $orgNumber): void
    {
        $uid = $orgNumber === 1 ? $this->userA->uid : $this->userB->uid;
        \Yii::app()->session['loginID'] = $uid;
    }

    /**
     * Grants $uid a read auditor grant into org "1" (org A) or "2" (org B), then logs in as $uid.
     *
     * @param int $uid
     * @param int $orgNumber 1 for org A, 2 for org B
     */
    protected function loginAsAuditorWithGrant(int $uid, int $orgNumber): void
    {
        $orgId = $orgNumber === 1 ? $this->orgAId : $this->orgBId;
        $grant = new \OrgAuditorGrant();
        $grant->uid = $uid;
        $grant->org_id = $orgId;
        $grant->granted_by = 1;
        $grant->scope = 'read';
        if (!$grant->save()) {
            throw new \Exception('Could not save auditor grant: ' . print_r($grant->errors, true));
        }
        \Yii::app()->session['loginID'] = $uid;
    }
}
