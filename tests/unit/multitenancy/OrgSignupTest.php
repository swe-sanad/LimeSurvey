<?php

namespace ls\tests;

use LimeSurvey\Models\Services\OrgSignup;

/**
 * Self-service org signup (docs/multitenancy/PHASE1-DESIGN.md Workflow A).
 *
 * NOTE: requires the Dockerized PHP 8.1 + DB test env (tests/README.md), same
 * as IsolationTest — cannot run from this environment (no Docker daemon,
 * local PHP is 8.4).
 */
class OrgSignupTest extends TestBaseClass
{
    /** @var int[] org ids created during the test, cleaned up in tearDown */
    private $createdOrgIds = [];

    /** @var int[] user ids created during the test, cleaned up in tearDown */
    private $createdUserIds = [];

    public function setUp(): void
    {
        parent::setUp();
        \Yii::app()->session['loginID'] = null;
    }

    public function tearDown(): void
    {
        foreach ($this->createdUserIds as $uid) {
            \Permission::model()->deleteAllByAttributes(['uid' => $uid]);
            \User::model()->findByPk($uid)?->delete();
        }
        foreach ($this->createdOrgIds as $orgId) {
            \Organization::model()->findByPk($orgId)?->delete();
        }
        \Yii::app()->session['loginID'] = 1;
        parent::tearDown();
    }

    public function testRegisterCreatesIsolatedOrgAndActiveOrgAdmin()
    {
        $result = (new OrgSignup())->register($this->validInput());

        $this->assertTrue($result['success'], print_r($result['errors'] ?? [], true));
        $user = $result['user'];
        $this->createdUserIds[] = $user->uid;
        $this->createdOrgIds[] = $user->owner_org_id;

        $this->assertNotEmpty($user->owner_org_id);
        $this->assertEquals(1, (int) $user->user_status, 'new org-admin must be active immediately');

        $this->assertFalse(
            \Permission::model()->hasGlobalPermission('superadmin', 'create', $user->uid),
            'org-admin must never hold the global superadmin permission'
        );
        $this->assertTrue(\Permission::model()->hasGlobalPermission('surveys', 'create', $user->uid));
        $this->assertTrue(\Permission::model()->hasGlobalPermission('users', 'create', $user->uid));
    }

    public function testSecondSignupCreatesADifferentOrg()
    {
        $first = (new OrgSignup())->register($this->validInput('first'));
        $this->assertTrue($first['success'], print_r($first['errors'] ?? [], true));
        $this->createdUserIds[] = $first['user']->uid;
        $this->createdOrgIds[] = $first['user']->owner_org_id;

        $second = (new OrgSignup())->register($this->validInput('second'));
        $this->assertTrue($second['success'], print_r($second['errors'] ?? [], true));
        $this->createdUserIds[] = $second['user']->uid;
        $this->createdOrgIds[] = $second['user']->owner_org_id;

        $this->assertNotEquals(
            $first['user']->owner_org_id,
            $second['user']->owner_org_id,
            'each signup must get its own isolated organization'
        );
    }

    public function testDuplicateEmailIsRejected()
    {
        // validInput() mints a fresh unique email on every call, so the duplicate
        // must come from reusing ONE input — calling validInput('dupe') twice would
        // produce two different emails and both would (correctly) succeed.
        $input = $this->validInput('dupe');

        $first = (new OrgSignup())->register($input);
        $this->assertTrue($first['success'], print_r($first['errors'] ?? [], true));
        $this->createdUserIds[] = $first['user']->uid;
        $this->createdOrgIds[] = $first['user']->owner_org_id;

        $second = (new OrgSignup())->register($input);
        $this->assertFalse($second['success']);
        $this->assertArrayHasKey('email', $second['errors']);
    }

    public function testFailedUserCreateRollsBackTheOrganization()
    {
        $orgCountBefore = \Organization::model()->count();

        $input = $this->validInput('rollback');
        // Passes OrgSignup's own field-presence validation but fails the
        // User model's own length rule ('full_name' max 50) — this exercises
        // the transactional rollback, not the pre-check.
        $input['full_name'] = str_repeat('x', 60);

        $result = (new OrgSignup())->register($input);

        $this->assertFalse($result['success']);
        $this->assertEquals(
            $orgCountBefore,
            \Organization::model()->count(),
            'a failed user create must leave no orphan organization row'
        );
    }

    private function validInput(string $suffix = 'a'): array
    {
        $unique = uniqid($suffix . '_');
        return [
            'org_name' => 'Signup Test Org ' . $unique,
            'full_name' => 'Signup Test User',
            'email' => 'signup-test-' . $unique . '@example.com',
            'password' => 'CorrectHorse!9',
            'password_confirm' => 'CorrectHorse!9',
        ];
    }
}
