<?php

use LimeSurvey\Models\Services\OrgInviteService;

/**
 * Organization team management (Phase 1 multi-tenancy, Workflow C): lets an
 * org-admin see the current org's members, invite new members by email, and
 * revoke a pending invite or deactivate a member.
 *
 * Every action here is explicitly scoped to TenantContext::currentOrgId() —
 * never trust an id from the request without checking it belongs to the
 * caller's own org. LSBaseController::run() already requires a login.
 */
class TeamController extends LSBaseController
{
    /** @inheritdoc */
    public function filters()
    {
        return [
            'postOnly + invite, revokeInvite, deactivate',
        ];
    }

    /**
     * Shows the current org's team: members + pending invites + the invite form.
     */
    public function actionIndex()
    {
        if (!Permission::model()->hasGlobalPermission('users', 'read')) {
            return $this->renderPartial(
                'partial/error',
                ['errors' => [gT('You do not have permission to access this page.')], 'noButton' => true]
            );
        }

        $orgId = TenantContext::currentOrgId();
        if ($orgId === null) {
            return $this->renderPartial(
                'partial/error',
                ['errors' => [gT('Team management is only available to organization members.')], 'noButton' => true]
            );
        }

        // User::search() is already org-scoped for a non-superadmin caller (see User::search()).
        $model = new User('search');
        $model->setAttributes(App()->getRequest()->getParam('User'), false);

        $invites = OrgInvite::model()->findAllByAttributes([
            'org_id' => $orgId,
            'status' => OrgInvite::STATUS_PENDING,
        ]);

        return $this->render('index', [
            'model' => $model,
            'invites' => $invites,
            'canInvite' => Permission::model()->hasGlobalPermission('users', 'create'),
            'canManage' => Permission::model()->hasGlobalPermission('users', 'update'),
        ]);
    }

    /**
     * Invites a new member by email into the caller's own org.
     */
    public function actionInvite()
    {
        if (!Permission::model()->hasGlobalPermission('users', 'create')) {
            App()->user->setFlash('error', gT('You do not have permission to access this page.'));
            $this->redirect(App()->createUrl('team/index'));
            return;
        }

        $orgId = TenantContext::currentOrgId();
        if ($orgId === null) {
            App()->user->setFlash('error', gT('Team invites are only available to organization members.'));
            $this->redirect(App()->createUrl('team/index'));
            return;
        }

        $email = trim((string) App()->request->getPost('email', ''));
        try {
            $invite = (new OrgInviteService())->createInvite($orgId, $email, (int) App()->user->getId());
            App()->user->setFlash('success', sprintf(gT('An invitation was sent to %s.'), $invite->email));
        } catch (DomainException | RuntimeException $e) {
            App()->user->setFlash('error', $e->getMessage());
        }

        $this->redirect(App()->createUrl('team/index'));
    }

    /**
     * Revokes a pending invite. The invite must belong to the caller's own org.
     */
    public function actionRevokeInvite()
    {
        if (!Permission::model()->hasGlobalPermission('users', 'update')) {
            throw new CHttpException(403, gT('You do not have permission to access this page.'));
        }

        $id = (int) App()->request->getPost('id');
        $invite = OrgInvite::model()->findByPk($id);
        if ($invite === null) {
            throw new CHttpException(404);
        }

        $orgId = TenantContext::currentOrgId();
        if ($orgId === null || (int) $invite->org_id !== $orgId) {
            throw new CHttpException(403, gT('You do not have permission to access this invitation.'));
        }

        $invite->status = OrgInvite::STATUS_REVOKED;
        if (!$invite->save(false, ['status'])) {
            App()->user->setFlash('error', gT('The invitation could not be revoked.'));
            $this->redirect(App()->createUrl('team/index'));
            return;
        }

        App()->user->setFlash('success', gT('Invitation revoked.'));
        $this->redirect(App()->createUrl('team/index'));
    }

    /**
     * Deactivates a member of the caller's own org. Cannot deactivate self or a superadmin.
     */
    public function actionDeactivate()
    {
        if (!Permission::model()->hasGlobalPermission('users', 'update')) {
            throw new CHttpException(403, gT('You do not have permission to access this page.'));
        }

        $uid = (int) App()->request->getPost('uid');
        $target = User::model()->findByPk($uid);
        if ($target === null) {
            throw new CHttpException(404);
        }

        $orgId = TenantContext::currentOrgId();
        if (
            $orgId === null
            || (int) $target->owner_org_id !== $orgId
            || $uid === (int) App()->user->getId()
            || Permission::model()->hasGlobalPermission('superadmin', 'read', $uid)
        ) {
            throw new CHttpException(403, gT('You do not have permission to deactivate this user.'));
        }

        $target->setActivationStatus('deactivate');

        App()->user->setFlash('success', gT('User deactivated.'));
        $this->redirect(App()->createUrl('team/index'));
    }
}
