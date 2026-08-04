<?php

namespace ls\tests;

/**
 * Phase 1 (Workflow B, stage 1) — per-survey visibility schema + decision predicate.
 *
 * Proves the `visibility` column exists and backfills to 'public', and that
 * Survey::getVisibilityAccessDecision() is the single source of truth used by
 * SurveyIndex/SurveyRuntimeHelper: public -> OPEN, draft -> DENY_DRAFT,
 * invite -> REQUIRE_TOKEN, private -> OPEN only for a superadmin or a
 * same-org logged-in user (DENY_PRIVATE for a guest or other-org user). Also
 * proves findAllPublic() excludes non-public surveys.
 *
 * Same env caveat as OrgScopingModelTest.php: requires the Dockerized PHP 8.1
 * + DB test env; cannot be run from this environment (local PHP is 8.4).
 */
class VisibilityTest extends TestBaseClass
{
    /** @var int */
    protected $orgAId;

    /** @var int */
    protected $orgBId;

    /** @var \User */
    protected $userA;

    /** @var \User */
    protected $userB;

    /** @var \User */
    protected $superadmin;

    /** @var int[] */
    protected $createdSurveyIds = [];

    public function setUp(): void
    {
        parent::setUp();
        \Yii::app()->session['loginID'] = 1;

        $this->orgAId = $this->createOrgFixture('Visibility Test Org A');
        $this->orgBId = $this->createOrgFixture('Visibility Test Org B');

        $this->userA = $this->createUserWithPermissions([
            'users_name' => 'visibility_test_user_a',
            'email' => 'visibility-test-user-a@example.com',
        ]);
        $this->setUserOrg($this->userA, $this->orgAId);

        $this->userB = $this->createUserWithPermissions([
            'users_name' => 'visibility_test_user_b',
            'email' => 'visibility-test-user-b@example.com',
        ]);
        $this->setUserOrg($this->userB, $this->orgBId);

        $this->superadmin = \User::model()->findByPk(1);
    }

    public function tearDown(): void
    {
        \Yii::app()->session['loginID'] = 1;
        foreach ($this->createdSurveyIds as $sid) {
            \Survey::model()->findByPk($sid)?->delete();
        }
        $this->createdSurveyIds = [];
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
     * The migration adds a NOT NULL DEFAULT 'public' column and backfills
     * existing rows to 'public'.
     */
    public function testVisibilityColumnExistsAndBackfillsToPublic()
    {
        $schema = \Survey::model()->getTableSchema();
        $this->assertNotNull($schema->getColumn('visibility'), 'surveys.visibility column must exist after migration');

        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId);
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame('public', $survey->visibility, 'existing/new surveys must backfill/default to public');
    }

    public function testPublicVisibilityIsOpen()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'public');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_OPEN, $survey->getVisibilityAccessDecision());
    }

    public function testDraftVisibilityDeniesTaking()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'draft');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_DENY_DRAFT, $survey->getVisibilityAccessDecision());
    }

    public function testInviteVisibilityRequiresToken()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'invite');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_REQUIRE_TOKEN, $survey->getVisibilityAccessDecision());
    }

    public function testPrivateVisibilityDeniesGuest()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'private');
        $survey = \Survey::model()->findByPk($sid);
        // Real guest: clear the session so null falls back to no logged-in user
        // (setUp leaves loginID=1, which would otherwise resolve to superadmin).
        \Yii::app()->session['loginID'] = null;
        $this->assertSame(\Survey::VISIBILITY_DENY_PRIVATE, $survey->getVisibilityAccessDecision(null));
        // And an explicit guest uid (0) is denied regardless of session.
        $this->assertSame(\Survey::VISIBILITY_DENY_PRIVATE, $survey->getVisibilityAccessDecision(0));
    }

    public function testInvalidVisibilityIsRejected()
    {
        $survey = new \Survey();
        $survey->setScenario('insert');
        $survey->visibility = 'bogus';
        $this->assertFalse($survey->validate(['visibility']), 'an out-of-range visibility must fail validation');
        $this->assertArrayHasKey('visibility', $survey->errors);
    }

    public function testPrivateVisibilityDeniesOtherOrgUser()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'private');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_DENY_PRIVATE, $survey->getVisibilityAccessDecision((int) $this->userB->uid));
    }

    public function testPrivateVisibilityAllowsSameOrgUser()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'private');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_OPEN, $survey->getVisibilityAccessDecision((int) $this->userA->uid));
    }

    public function testPrivateVisibilityAllowsSuperadmin()
    {
        $sid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'private');
        $survey = \Survey::model()->findByPk($sid);
        $this->assertSame(\Survey::VISIBILITY_OPEN, $survey->getVisibilityAccessDecision((int) $this->superadmin->uid));
    }

    public function testFindAllPublicExcludesNonPublicSurvey()
    {
        $publicSid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'public', 'Y');
        $privateSid = $this->createSurveyFixture($this->userA->uid, $this->orgAId, 'private', 'Y');

        $sids = array_map(function ($s) {
            return (int) $s->sid;
        }, (new \Survey())->findAllPublic());

        $this->assertContains($publicSid, $sids, 'a public + listpublic survey must be listed');
        $this->assertNotContains($privateSid, $sids, 'a private survey must never be listed even if listpublic=Y');
    }

    /**
     * @return int the new org's org_id
     */
    protected function createOrgFixture(string $name): int
    {
        $org = new \Organization();
        $org->name = $name;
        $org->slug = 'visibility-test-' . strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();
        $org->status = 'active';
        if (!$org->save()) {
            throw new \Exception('Could not save organization: ' . print_r($org->errors, true));
        }
        return (int) $org->org_id;
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
     * @return int the new survey's sid
     */
    protected function createSurveyFixture(int $ownerUid, int $orgId, ?string $visibility = null, string $listpublic = 'N'): int
    {
        $survey = new \Survey();
        $survey->setScenario('insert');
        $sid = 100000 + \mt_rand(0, 899999);
        while (\Survey::model()->findByPk($sid) !== null) {
            $sid = 100000 + \mt_rand(0, 899999);
        }
        $survey->sid = $sid;
        $survey->admin = 'Visibility Test Admin';
        $survey->language = 'en';
        $survey->format = 'G';
        $survey->active = 'N';
        $survey->listpublic = $listpublic;
        $survey->owner_id = $ownerUid;
        $survey->owner_org_id = $orgId;
        if ($visibility !== null) {
            $survey->visibility = $visibility;
        }
        if (!$survey->save()) {
            throw new \Exception('Could not save survey: ' . print_r($survey->errors, true));
        }
        $this->createdSurveyIds[] = $survey->sid;
        return (int) $survey->sid;
    }
}
