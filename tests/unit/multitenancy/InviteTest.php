<?php

namespace ls\tests;

use LimeSurvey\Models\Services\OrgInviteService;

/**
 * Organization team invites (Phase 1 multi-tenancy, Workflow C).
 *
 * State machine under test: OrgInvite pending -> accepted | revoked (or
 * pending -> expired by clock, a passive transition). Every legal
 * transition, illegal transition, and per-state invariant is driven through
 * the real OrgInviteService / OrgInvite model, asserting persisted rows —
 * never mock call counts or source text. Also covers the UserManagement
 * cross-org IDOR guard that this workflow hardened (item 8), driven through
 * the real public chokepoint (UserManagementController::loadModel(), which
 * delegates to the private getUserOr403()).
 *
 * NOTE: requires the Dockerized PHP 8.1 + DB test env (tests/README.md) —
 * cannot run from this environment (no Docker daemon, local PHP is 8.4).
 */
class InviteTest extends TestBaseClass
{
    /** @var int */
    private $orgId;

    /** @var int */
    private $otherOrgId;

    /** @var \User */
    private $orgAdmin;

    /** @var int[] invite ids created during the test, cleaned up in tearDown */
    private $createdInviteIds = [];

    /** @var int[] user ids created during the test, cleaned up in tearDown */
    private $createdUserIds = [];

    public function setUp(): void
    {
        parent::setUp();
        \Yii::app()->session['loginID'] = 1;

        $this->orgId = $this->createOrgFixture('Invite Test Org');
        $this->otherOrgId = $this->createOrgFixture('Invite Test Other Org');

        $this->orgAdmin = $this->createUserWithPermissions([
            'users_name' => 'invite_test_org_admin_' . uniqid(),
            'email' => 'invite-test-org-admin-' . uniqid() . '@example.com',
        ], ['users' => ['read' => 1, 'create' => 1, 'update' => 1]]);
        $this->setUserOrg($this->orgAdmin, $this->orgId);

        // The accept() flow itself runs with no acting admin session (the
        // invitee is anonymous) — tests that call accept() clear loginID
        // right before the call; createInvite()/IDOR tests need an acting
        // session, so setUp leaves loginID=1 (superadmin) by default.
    }

    public function tearDown(): void
    {
        \Yii::app()->session['loginID'] = 1;
        foreach ($this->createdUserIds as $uid) {
            \Permission::model()->deleteAllByAttributes(['uid' => $uid]);
            \User::model()->findByPk($uid)?->delete();
        }
        foreach ($this->createdInviteIds as $id) {
            \OrgInvite::model()->findByPk($id)?->delete();
        }
        \User::model()->findByPk($this->orgAdmin->uid)?->delete();
        \Organization::model()->findByPk($this->orgId)?->delete();
        \Organization::model()->findByPk($this->otherOrgId)?->delete();
        parent::tearDown();
    }

    // ---- 1. createInvite() writes a pending row: token + expiry + org scope ----

    public function testCreateInviteWritesPendingRowWithTokenAndExpiryScopedToInviterOrg()
    {
        $email = 'pending-' . uniqid() . '@example.com';

        $invite = $this->createInviteFixtureViaService($email);

        $this->assertSame($this->orgId, (int) $invite->org_id, 'invite must be scoped to the inviter org');
        $this->assertSame(\OrgInvite::STATUS_PENDING, $invite->status);
        $this->assertSame(64, strlen((string) $invite->token), 'token must be exactly 64 chars');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', (string) $invite->token);
        $this->assertNotEmpty($invite->expires);
        $this->assertGreaterThan(gmdate('Y-m-d H:i:s'), $invite->expires, 'expiry must be in the future');
    }

    // ---- 2 & 3. accept() lands the member in the correct org with curated permissions ----

    public function testAcceptCreatesActiveUserInInviteOrgWithPasswordSet()
    {
        $email = 'accept-' . uniqid() . '@example.com';
        $invite = $this->makePendingInvite($email, (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;

        \Yii::app()->session['loginID'] = null;
        $user = (new OrgInviteService())->accept($invite->token, 'CorrectHorse!9', 'Invited Member');
        $this->createdUserIds[] = $user->uid;

        $this->assertSame($this->orgId, (int) $user->owner_org_id, 'member must land in the invite org, not any other');
        $this->assertNotSame($this->otherOrgId, (int) $user->owner_org_id);
        $this->assertEquals(1, (int) $user->user_status, 'accepted member must be active immediately');
        $this->assertTrue($user->checkPassword('CorrectHorse!9'), 'the submitted password must actually be set on the user');
        $this->assertFalse($user->checkPassword('SomeOtherPassword!9'));

        $reloaded = \User::model()->findByPk($user->uid);
        $this->assertSame($this->orgId, (int) $reloaded->owner_org_id, 'org placement must persist, not just be in-memory');
    }

    public function testAcceptGrantsMemberPermissionRowsAndExcludesPrivilegedKeys()
    {
        $email = 'perms-' . uniqid() . '@example.com';
        $invite = $this->makePendingInvite($email, (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;

        \Yii::app()->session['loginID'] = null;
        $user = (new OrgInviteService())->accept($invite->token, 'CorrectHorse!9', 'Invited Member');
        $this->createdUserIds[] = $user->uid;

        $rows = $this->indexPermissionRowsByKey((int) $user->uid);

        $this->assertArrayHasKey('surveys', $rows, 'surveys permission row must exist for a member');
        $surveys = $rows['surveys'];
        $this->assertSame(1, (int) $surveys->create_p);
        $this->assertSame(1, (int) $surveys->read_p);
        $this->assertSame(1, (int) $surveys->update_p);
        $this->assertSame(1, (int) $surveys->delete_p);
        $this->assertSame(1, (int) $surveys->export_p);
        $this->assertSame(0, (int) $surveys->import_p);

        $this->assertArrayHasKey('templates', $rows);
        $this->assertSame(1, (int) $rows['templates']->read_p);
        $this->assertSame(0, (int) $rows['templates']->create_p);
        $this->assertSame(0, (int) $rows['templates']->update_p);
        $this->assertSame(0, (int) $rows['templates']->delete_p);

        $this->assertArrayHasKey('labelsets', $rows);
        $this->assertSame(1, (int) $rows['labelsets']->read_p);
        $this->assertSame(0, (int) $rows['labelsets']->update_p);

        // Critical privilege-escalation guard: none of these permission keys
        // may ever get a row at all — not even an all-false one — for a
        // freshly accepted member.
        foreach (['superadmin', 'users', 'usergroups', 'settings'] as $forbiddenKey) {
            $this->assertArrayNotHasKey(
                $forbiddenKey,
                $rows,
                "accepted member must NEVER have a '$forbiddenKey' permission row"
            );
        }

        // Belt-and-braces: the behavior-level surface agrees with the raw rows.
        $this->assertFalse(\Permission::model()->hasGlobalPermission('superadmin', 'read', $user->uid));
        $this->assertFalse(\Permission::model()->hasGlobalPermission('users', 'read', $user->uid));
        $this->assertFalse(\Permission::model()->hasGlobalPermission('usergroups', 'read', $user->uid));
        $this->assertFalse(\Permission::model()->hasGlobalPermission('settings', 'read', $user->uid));
    }

    // ---- 4, 5, 6. illegal transitions: expired / already-accepted / revoked are rejected ----

    public function testAcceptRejectsExpiredInviteAndCreatesNoUser()
    {
        $email = 'expired-' . uniqid() . '@example.com';
        $invite = $this->makePendingInvite(
            $email,
            (int) $this->orgAdmin->uid,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );
        $this->createdInviteIds[] = $invite->id;

        \Yii::app()->session['loginID'] = null;
        try {
            (new OrgInviteService())->accept($invite->token, 'CorrectHorse!9', 'Expired Attempt');
            $this->fail('Expected a DomainException for an expired invite');
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertNull(
            \User::model()->findByAttributes(['email' => $email]),
            'no user may be created from an expired invite'
        );
    }

    public function testAcceptRejectsAlreadyAcceptedInviteSingleUse()
    {
        $email = 'reused-' . uniqid() . '@example.com';
        $invite = $this->makePendingInvite($email, (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;

        \Yii::app()->session['loginID'] = null;
        $firstUser = (new OrgInviteService())->accept($invite->token, 'CorrectHorse!9', 'First Accept');
        $this->createdUserIds[] = $firstUser->uid;

        try {
            (new OrgInviteService())->accept($invite->token, 'AnotherPass!9', 'Second Accept');
            $this->fail('Expected a DomainException on re-use of an already-accepted token');
        } catch (\DomainException $e) {
            // expected
        }

        // Exactly one user was created for this email — the token was truly single-use.
        $criteria = new \CDbCriteria();
        $criteria->compare('email', $email);
        $this->assertSame(1, \User::model()->count($criteria));
    }

    public function testAcceptRejectsRevokedInviteAndCreatesNoUser()
    {
        $email = 'revoked-' . uniqid() . '@example.com';
        $invite = $this->makePendingInvite($email, (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;
        $invite->status = \OrgInvite::STATUS_REVOKED;
        if (!$invite->save(false, ['status'])) {
            throw new \Exception('Could not revoke invite fixture: ' . print_r($invite->errors, true));
        }

        \Yii::app()->session['loginID'] = null;
        try {
            (new OrgInviteService())->accept($invite->token, 'CorrectHorse!9', 'Revoked Attempt');
            $this->fail('Expected a DomainException for a revoked invite');
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertNull(
            \User::model()->findByAttributes(['email' => $email]),
            'no user may be created from a revoked invite'
        );
    }

    // ---- 7. createInvite() dedupes ----

    public function testCreateInviteRejectsSecondPendingInviteForSameOrgAndEmail()
    {
        $email = 'dupe-' . uniqid() . '@example.com';
        $first = $this->createInviteFixtureViaService($email);
        $this->assertSame(\OrgInvite::STATUS_PENDING, $first->status);

        $this->expectException(\DomainException::class);
        (new OrgInviteService())->createInvite($this->orgId, $email, (int) $this->orgAdmin->uid);
    }

    public function testCreateInviteRejectsEmailAlreadyBelongingToAnOrgMember()
    {
        $this->expectException(\DomainException::class);
        (new OrgInviteService())->createInvite($this->orgId, $this->orgAdmin->email, (int) $this->orgAdmin->uid);
    }

    // ---- 8. cross-org IDOR: UserManagementController's org guard ----

    public function testCrossOrgUserCannotLoadUserInAnotherOrg()
    {
        $memberA = $this->createUserWithPermissions([
            'users_name' => 'invite_test_member_a_' . uniqid(),
            'email' => 'invite-test-member-a-' . uniqid() . '@example.com',
        ], ['users' => ['read' => 1]]);
        $this->setUserOrg($memberA, $this->orgId);
        $this->createdUserIds[] = $memberA->uid;

        $memberB = $this->createUserWithPermissions([
            'users_name' => 'invite_test_member_b_' . uniqid(),
            'email' => 'invite-test-member-b-' . uniqid() . '@example.com',
        ]);
        $this->setUserOrg($memberB, $this->otherOrgId);
        $this->createdUserIds[] = $memberB->uid;

        \Yii::app()->session['loginID'] = $memberA->uid;
        $controller = new \UserManagementController('userManagement');

        try {
            $controller->loadModel((int) $memberB->uid);
            $this->fail('Expected a 403 CHttpException for a cross-org user id');
        } catch (\CHttpException $e) {
            $this->assertSame(403, $e->statusCode);
        }
    }

    public function testSameOrgUserCanLoadUserInOwnOrg()
    {
        $memberA = $this->createUserWithPermissions([
            'users_name' => 'invite_test_member_same_' . uniqid(),
            'email' => 'invite-test-member-same-' . uniqid() . '@example.com',
        ], ['users' => ['read' => 1]]);
        $this->setUserOrg($memberA, $this->orgId);
        $this->createdUserIds[] = $memberA->uid;

        \Yii::app()->session['loginID'] = $memberA->uid;
        $controller = new \UserManagementController('userManagement');

        $resolved = $controller->loadModel((int) $this->orgAdmin->uid);

        $this->assertSame((int) $this->orgAdmin->uid, (int) $resolved->uid);
    }

    public function testSuperadminCanLoadUserInAnyOrg()
    {
        $memberB = $this->createUserWithPermissions([
            'users_name' => 'invite_test_member_superadmin_' . uniqid(),
            'email' => 'invite-test-member-superadmin-' . uniqid() . '@example.com',
        ]);
        $this->setUserOrg($memberB, $this->otherOrgId);
        $this->createdUserIds[] = $memberB->uid;

        // uid 1 is the platform superadmin (TenantContext-null): must reach any org's user.
        \Yii::app()->session['loginID'] = 1;
        $controller = new \UserManagementController('userManagement');

        $resolved = $controller->loadModel((int) $memberB->uid);

        $this->assertSame((int) $memberB->uid, (int) $resolved->uid);
    }

    public function testOrglessNonSuperadminCannotLoadAnotherOrglessUser()
    {
        // Regression lock: two "orgless" (owner_org_id = NULL) non-superadmin accounts — a state
        // the platform superadmin can produce by creating users without an explicit org. A naive
        // int-cast compare makes both sides 0 and silently GRANTS access; the guard must 403.
        $orglessActor = $this->createUserWithPermissions([
            'users_name' => 'invite_test_orgless_actor_' . uniqid(),
            'email' => 'invite-test-orgless-actor-' . uniqid() . '@example.com',
        ], ['users' => ['read' => 1]]);
        $orglessActor->owner_org_id = null;
        $orglessActor->save(false);
        $this->createdUserIds[] = $orglessActor->uid;

        $orglessTarget = $this->createUserWithPermissions([
            'users_name' => 'invite_test_orgless_target_' . uniqid(),
            'email' => 'invite-test-orgless-target-' . uniqid() . '@example.com',
        ]);
        $orglessTarget->owner_org_id = null;
        $orglessTarget->save(false);
        $this->createdUserIds[] = $orglessTarget->uid;

        \Yii::app()->session['loginID'] = $orglessActor->uid;
        $controller = new \UserManagementController('userManagement');

        try {
            $controller->loadModel((int) $orglessTarget->uid);
            $this->fail('Expected a 403 — an orgless non-superadmin must not reach another orgless user');
        } catch (\CHttpException $e) {
            $this->assertSame(403, $e->statusCode);
        }
    }

    // ---- 10. TeamController per-record actions (revoke / deactivate) are org-scoped ----

    public function testTeamDeactivateRejectsCrossOrgUser()
    {
        $memberB = $this->createUserWithPermissions([
            'users_name' => 'invite_test_team_x_' . uniqid(),
            'email' => 'invite-test-team-x-' . uniqid() . '@example.com',
        ]);
        $this->setUserOrg($memberB, $this->otherOrgId);
        $this->createdUserIds[] = $memberB->uid;

        $this->assertTeamDeactivate403((int) $memberB->uid, 'a member of another org');
        $this->assertEquals(1, (int) \User::model()->findByPk($memberB->uid)->user_status, 'cross-org target must stay active');
    }

    public function testTeamDeactivateRejectsSelf()
    {
        $this->assertTeamDeactivate403((int) $this->orgAdmin->uid, 'the acting admin themselves');
    }

    public function testTeamDeactivateRejectsSuperadmin()
    {
        $superInOrg = $this->createUserWithPermissions([
            'users_name' => 'invite_test_team_super_' . uniqid(),
            'email' => 'invite-test-team-super-' . uniqid() . '@example.com',
        ], ['superadmin' => ['read' => 1]]);
        $this->setUserOrg($superInOrg, $this->orgId);
        $this->createdUserIds[] = $superInOrg->uid;

        $this->assertTeamDeactivate403((int) $superInOrg->uid, 'a superadmin (even in the same org)');
        $this->assertEquals(1, (int) \User::model()->findByPk($superInOrg->uid)->user_status, 'superadmin target must stay active');
    }

    public function testTeamRevokeInviteRejectsCrossOrgInvite()
    {
        // A pending invite owned by ANOTHER org.
        $invite = new \OrgInvite();
        $invite->org_id = $this->otherOrgId;
        $invite->email = 'cross-org-invite-' . uniqid() . '@example.com';
        $invite->token = \randomChars(64);
        $invite->status = \OrgInvite::STATUS_PENDING;
        $invite->created = gmdate('Y-m-d H:i:s');
        $invite->expires = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        if (!$invite->save()) {
            throw new \Exception('Could not save cross-org invite fixture: ' . print_r($invite->errors, true));
        }
        $this->createdInviteIds[] = $invite->id;

        \Yii::app()->session['loginID'] = $this->orgAdmin->uid;
        $_POST['id'] = $invite->id;
        $controller = new \TeamController('team');
        try {
            $controller->actionRevokeInvite();
            $this->fail('Expected a 403 — org-admin must not revoke another org\'s invite');
        } catch (\CHttpException $e) {
            $this->assertSame(403, $e->statusCode);
        } finally {
            unset($_POST['id']);
        }

        $this->assertSame(
            \OrgInvite::STATUS_PENDING,
            \OrgInvite::model()->findByPk($invite->id)->status,
            'the other org\'s invite must remain untouched'
        );
    }

    // ---- 9. token invalidity does not leak: OrgInvite::isValid() ----

    public function testIsValidTrueForPendingUnexpiredInvite()
    {
        $invite = $this->makePendingInvite('valid-' . uniqid() . '@example.com', (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;

        $this->assertTrue($invite->isValid());
    }

    public function testIsValidFalseForExpiredInvite()
    {
        $invite = $this->makePendingInvite(
            'expiry-check-' . uniqid() . '@example.com',
            (int) $this->orgAdmin->uid,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );
        $this->createdInviteIds[] = $invite->id;

        $this->assertFalse($invite->isValid());
    }

    public function testIsValidFalseForAcceptedInvite()
    {
        $invite = $this->makePendingInvite('accepted-check-' . uniqid() . '@example.com', (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;
        $invite->status = \OrgInvite::STATUS_ACCEPTED;
        $invite->accepted_at = gmdate('Y-m-d H:i:s');
        if (!$invite->save(false, ['status', 'accepted_at'])) {
            throw new \Exception('Could not mark invite fixture accepted: ' . print_r($invite->errors, true));
        }

        $this->assertFalse($invite->isValid());
    }

    public function testIsValidFalseForRevokedInvite()
    {
        $invite = $this->makePendingInvite('revoked-check-' . uniqid() . '@example.com', (int) $this->orgAdmin->uid);
        $this->createdInviteIds[] = $invite->id;
        $invite->status = \OrgInvite::STATUS_REVOKED;
        if (!$invite->save(false, ['status'])) {
            throw new \Exception('Could not mark invite fixture revoked: ' . print_r($invite->errors, true));
        }

        $this->assertFalse($invite->isValid());
    }

    // ---- fixtures ----

    /**
     * Drives TeamController::actionDeactivate as $this->orgAdmin against $uid and asserts a 403
     * is thrown before any state change. The 403 path throws before the action's final redirect,
     * so this exercises the real guard without the redirect terminating the test.
     */
    private function assertTeamDeactivate403(int $uid, string $why): void
    {
        \Yii::app()->session['loginID'] = $this->orgAdmin->uid;
        $_POST['uid'] = $uid;
        $controller = new \TeamController('team');
        try {
            $controller->actionDeactivate();
            $this->fail('Expected a 403 CHttpException when deactivating ' . $why);
        } catch (\CHttpException $e) {
            $this->assertSame(403, $e->statusCode);
        } finally {
            unset($_POST['uid']);
        }
    }

    private function createOrgFixture(string $name): int
    {
        $org = new \Organization();
        $org->name = $name;
        $org->slug = 'invite-test-' . strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();
        $org->status = 'active';
        if (!$org->save()) {
            throw new \Exception('Could not save organization: ' . print_r($org->errors, true));
        }
        return (int) $org->org_id;
    }

    private function setUserOrg(\User $user, int $orgId): void
    {
        $user->owner_org_id = $orgId;
        if (!$user->save(false)) {
            throw new \Exception('Could not save user owner_org_id: ' . print_r($user->errors, true));
        }
    }

    private function makePendingInvite(string $email, ?int $invitedBy = null, ?string $expires = null): \OrgInvite
    {
        $invite = new \OrgInvite();
        $invite->org_id = $this->orgId;
        $invite->email = $email;
        $invite->token = \randomChars(64);
        $invite->invited_by = $invitedBy;
        $invite->status = \OrgInvite::STATUS_PENDING;
        $invite->created = gmdate('Y-m-d H:i:s');
        $invite->expires = $expires ?? gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        if (!$invite->save()) {
            throw new \Exception('Could not save invite fixture: ' . print_r($invite->errors, true));
        }
        return $invite;
    }

    /**
     * Creates a pending invite through the real service (exercises validation +
     * dedupe + persistence together), tolerating the environment having no
     * working mail transport — the invite row is persisted regardless of
     * whether the (best-effort) notification email could be sent.
     */
    private function createInviteFixtureViaService(string $email): \OrgInvite
    {
        try {
            $invite = (new OrgInviteService())->createInvite($this->orgId, $email, (int) $this->orgAdmin->uid);
        } catch (\RuntimeException $e) {
            $invite = \OrgInvite::model()->findByAttributes(['org_id' => $this->orgId, 'email' => $email]);
        }
        $this->assertNotNull($invite, 'invite row must be persisted even if the notification email failed');
        $this->createdInviteIds[] = $invite->id;
        return $invite;
    }

    /**
     * @return array<string, \Permission> permission rows for $uid keyed by permission name
     */
    private function indexPermissionRowsByKey(int $uid): array
    {
        $rows = \Permission::model()->findAllByAttributes(['uid' => $uid, 'entity' => 'global', 'entity_id' => 0]);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->permission] = $row;
        }
        return $byKey;
    }
}
