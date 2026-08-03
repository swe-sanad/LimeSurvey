<?php

namespace ls\tests;

/**
 * Phase 1 (Workflow A, stage 1) — model-layer org isolation.
 *
 * Proves Survey::search()/getPermissionCriteria() and User::search() are
 * org-scoped (never leaking another org's rows to an org-scoped caller, while
 * the platform super-admin still sees across orgs), and that Survey/User
 * beforeSave() stamp owner_org_id from the creating user's org.
 *
 * Same env caveat as IsolationTest.php: requires the Dockerized PHP 8.1 + DB
 * test env; cannot be run from this environment (local PHP is 8.4).
 */
class OrgScopingModelTest extends TestBaseClass
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

    public function setUp(): void
    {
        parent::setUp();
        // Seed fixtures as the platform superadmin (uid 1).
        \Yii::app()->session['loginID'] = 1;

        $this->orgAId = $this->createOrgFixture('Org Scoping Test Org A');
        $this->orgBId = $this->createOrgFixture('Org Scoping Test Org B');

        $this->userA = $this->createUserWithPermissions([
            'users_name' => 'org_scoping_test_user_a',
            'email' => 'org-scoping-test-user-a@example.com',
        ], [
            // Global 'surveys' + 'users' read: without an org restriction this
            // would let userA see every org's surveys/users, not just their own.
            'surveys' => ['read' => 1],
            'usergroups' => ['read' => 1],
        ]);
        $this->setUserOrg($this->userA, $this->orgAId);

        $this->userB = $this->createUserWithPermissions([
            'users_name' => 'org_scoping_test_user_b',
            'email' => 'org-scoping-test-user-b@example.com',
        ]);
        $this->setUserOrg($this->userB, $this->orgBId);

        $this->surveyA = $this->createSurveyFixture($this->userA->uid, $this->orgAId);
        $this->surveyB = $this->createSurveyFixture($this->userB->uid, $this->orgBId);
    }

    public function tearDown(): void
    {
        \Yii::app()->session['loginID'] = 1;
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
        foreach ([$this->orgAId, $this->orgBId] as $orgId) {
            if ($orgId) {
                \Organization::model()->findByPk($orgId)?->delete();
            }
        }
        parent::tearDown();
    }

    /**
     * Survey::search() must exclude another org's surveys even though userA
     * holds a *global* 'surveys' read permission (which, pre-org-scoping,
     * would have shown every org's surveys).
     */
    public function testSurveySearchIsOrgScopedForOrgUser()
    {
        \Yii::app()->session['loginID'] = $this->userA->uid;

        $sids = $this->searchSurveySids();

        $this->assertContains($this->surveyA, $sids, 'org A user must see their own org survey');
        $this->assertNotContains($this->surveyB, $sids, 'org A user must never see org B survey');
    }

    /**
     * User::search() must exclude another org's users for an org-scoped caller.
     */
    public function testUserSearchIsOrgScopedForOrgUser()
    {
        \Yii::app()->session['loginID'] = $this->userA->uid;

        $uids = $this->searchUserUids();

        $this->assertContains((int) $this->userA->uid, $uids, 'org A user must see themselves');
        $this->assertNotContains((int) $this->userB->uid, $uids, 'org A user must never see org B user');
    }

    /**
     * The platform super-admin (uid 1, TenantContext-null) bypasses the org
     * restriction entirely and sees rows across both orgs.
     */
    public function testSuperadminSearchSpansBothOrgs()
    {
        \Yii::app()->session['loginID'] = 1;

        $sids = $this->searchSurveySids();
        $this->assertContains($this->surveyA, $sids, 'superadmin must see org A survey');
        $this->assertContains($this->surveyB, $sids, 'superadmin must see org B survey');

        $uids = $this->searchUserUids();
        $this->assertContains((int) $this->userA->uid, $uids, 'superadmin must see org A user');
        $this->assertContains((int) $this->userB->uid, $uids, 'superadmin must see org B user');
    }

    /**
     * Survey::beforeSave() stamps owner_org_id from the creating user's org
     * when it was not explicitly set.
     */
    public function testSurveyBeforeSaveStampsOwnerOrgFromCreator()
    {
        \Yii::app()->session['loginID'] = $this->userA->uid;

        $survey = new \Survey();
        $survey->setScenario('insert');
        $sid = 100000 + \mt_rand(0, 899999);
        while (\Survey::model()->findByPk($sid) !== null) {
            $sid = 100000 + \mt_rand(0, 899999);
        }
        $survey->sid = $sid;
        $survey->admin = 'Org Scoping Test Admin';
        $survey->language = 'en';
        $survey->format = 'G';
        $survey->active = 'N';
        $survey->owner_id = $this->userA->uid;
        // owner_org_id intentionally left unset.

        try {
            $this->assertTrue($survey->save(), print_r($survey->errors, true));
            $saved = \Survey::model()->findByPk($sid);
            $this->assertSame((int) $this->orgAId, (int) $saved->owner_org_id);
        } finally {
            \Survey::model()->findByPk($sid)?->delete();
        }
    }

    /**
     * User::beforeSave() stamps owner_org_id from the creating (session) user's
     * org when it was not explicitly set — a team member created by an
     * org-admin inherits the org.
     */
    public function testUserBeforeSaveStampsOwnerOrgFromSessionUser()
    {
        \Yii::app()->session['loginID'] = $this->userA->uid;

        $newUser = $this->createUserWithPermissions([
            'users_name' => 'org_scoping_test_inherited_user',
            'email' => 'org-scoping-test-inherited-user@example.com',
        ]);

        try {
            $saved = \User::model()->findByPk($newUser->uid);
            $this->assertSame((int) $this->orgAId, (int) $saved->owner_org_id);
        } finally {
            \User::model()->findByPk($newUser->uid)?->delete();
        }
    }

    /**
     * @return int[] sids returned by Survey::search() for the current session user
     */
    protected function searchSurveySids(): array
    {
        $provider = (new \Survey())->search();
        $sids = [];
        foreach ($provider->getData() as $survey) {
            $sids[] = (int) $survey->sid;
        }
        return $sids;
    }

    /**
     * @return int[] uids returned by User::search() for the current session user
     */
    protected function searchUserUids(): array
    {
        $provider = (new \User())->search();
        $uids = [];
        foreach ($provider->getData() as $user) {
            $uids[] = (int) $user->uid;
        }
        return $uids;
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
        $org->slug = 'org-scoping-test-' . strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();
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
        $sid = 100000 + \mt_rand(0, 899999);
        while (\Survey::model()->findByPk($sid) !== null) {
            $sid = 100000 + \mt_rand(0, 899999);
        }
        $survey->sid = $sid;
        $survey->admin = 'Org Scoping Test Admin';
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
}
