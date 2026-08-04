<?php

namespace LimeSurvey\Models\Services;

use CDbTransaction;
use LimeMailer;
use Organization;
use OrgInvite;
use Permission;
use Throwable;
use User;
use Yii;

/**
 * Organization team invites (Phase 1 multi-tenancy, Workflow C): an org-admin
 * invites a user by email; the invitee accepts via a tokenized link and
 * becomes a MEMBER User of that org (never an org-admin, never superadmin).
 *
 * MIRRORS OrgSignup's User-creation + Permission::setPermissions(..., true)
 * bypass style so the two grant paths can never structurally diverge. See
 * OrgSignup.php's class doc for why the bypass path can never grant
 * 'superadmin' to a non-superadmin acting session.
 */
class OrgInviteService
{
    /** @var int days an invite token remains valid */
    private const INVITE_TTL_DAYS = 7;

    /**
     * Permission keys granted to every accepted org member (HARDCODED — never
     * user-chosen, so there is no escalation path via the invite flow).
     * Deliberately excludes: users, usergroups, settings, superadmin, participantpanel.
     */
    private const MEMBER_PERMISSIONS = [
        'surveys' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true, 'import' => false, 'export' => true],
        'templates' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false, 'import' => false, 'export' => false],
        'labelsets' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false, 'import' => false, 'export' => false],
    ];

    /**
     * Creates a pending invite for $email in $orgId and sends the invite email.
     *
     * @throws \DomainException on invalid/duplicate email or a duplicate pending invite
     * @throws \RuntimeException if the invite row could not be saved. A notification-email
     *         failure does NOT throw — it is logged best-effort and the persisted invite
     *         remains the source of truth (so the caller's success message matches DB state).
     */
    public function createInvite(int $orgId, string $email, int $invitedByUid): OrgInvite
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException(gT('Please enter a valid email address.'));
        }
        // User.email is globally unique, so an invite for an email that already has ANY account
        // could never be accepted (accept()'s User save would fail). Reject early with accurate
        // feedback instead of creating a doomed invite the org-admin is never warned about.
        $existingUser = User::model()->findByAttributes(['email' => $email]);
        if ($existingUser !== null) {
            if ((int) $existingUser->owner_org_id === $orgId) {
                throw new \DomainException(gT('This email address already belongs to a member of this organization.'));
            }
            throw new \DomainException(gT('This email address is already registered to an account and cannot be invited.'));
        }
        // ponytail: SELECT-then-INSERT dedupe of pending invites is TOCTOU — two concurrent
        // submissions for the same (org,email) could both pass. Admin-triggered, worst case one
        // duplicate pending row; add a partial unique index on (org_id,email,status) if it matters.
        $existing = OrgInvite::model()->findByAttributes([
            'org_id' => $orgId,
            'email' => $email,
            'status' => OrgInvite::STATUS_PENDING,
        ]);
        if ($existing !== null && $existing->isValid()) {
            throw new \DomainException(gT('An invitation is already pending for this email address.'));
        }

        $invite = new OrgInvite();
        $invite->org_id = $orgId;
        $invite->email = $email;
        $invite->token = randomChars(64);
        $invite->invited_by = $invitedByUid;
        $invite->status = OrgInvite::STATUS_PENDING;
        $invite->created = gmdate('Y-m-d H:i:s');
        $invite->expires = gmdate('Y-m-d H:i:s', time() + self::INVITE_TTL_DAYS * 86400);
        if (!$invite->save()) {
            throw new \RuntimeException('Could not save invite: ' . print_r($invite->errors, true));
        }

        Yii::log('Org invite created: invite ' . $invite->id . ' org ' . $orgId . ' by user ' . $invitedByUid, 'info', 'application.orginvite');
        $this->sendInviteEmail($invite); // best-effort; logs on failure, never throws (see doc)

        return $invite;
    }

    /**
     * Accepts a pending invite: creates a member User in the invite's org, grants the
     * curated MEMBER_PERMISSIONS set, and marks the invite accepted. Transactional.
     *
     * @throws \DomainException if the invite does not exist, or is not pending/unexpired
     * @throws \RuntimeException if the user or invite row could not be saved
     */
    public function accept(string $token, string $password, ?string $fullName = null): User
    {
        $invite = OrgInvite::findByToken($token);
        if ($invite === null || !$invite->isValid()) {
            throw new \DomainException(gT('This invitation is invalid or has expired.'));
        }

        /** @var CDbTransaction $transaction */
        $transaction = Yii::app()->db->beginTransaction();
        try {
            $user = new User();
            $user->users_name = $this->deriveUsername($invite->email);
            $user->setPassword($password);
            $trimmedFullName = trim((string) $fullName);
            $user->full_name = $trimmedFullName !== '' ? $trimmedFullName : $user->users_name;
            $user->email = $invite->email;
            $user->parent_id = $invite->invited_by ?: 1;
            $user->lang = 'auto';
            $user->created = gmdate('Y-m-d H:i:s');
            $user->modified = gmdate('Y-m-d H:i:s');
            $user->user_status = true;
            // Explicit: never rely on User::beforeSave()'s TenantContext auto-stamp here —
            // there is no acting session for the invitee yet, so the org MUST come from
            // the invite row, not from a fallback that would leave it empty.
            $user->owner_org_id = $invite->org_id;
            if (!$user->save()) {
                throw new \RuntimeException('Could not save invited user: ' . print_r($user->errors, true));
            }

            $this->grantMemberPermissions((int) $user->uid);

            $invite->status = OrgInvite::STATUS_ACCEPTED;
            $invite->accepted_at = gmdate('Y-m-d H:i:s');
            if (!$invite->save(false, ['status', 'accepted_at'])) {
                throw new \RuntimeException('Could not mark invite accepted: ' . print_r($invite->errors, true));
            }

            $transaction->commit();
            Yii::log('Org invite accepted: user ' . $user->uid . ' org ' . $invite->org_id, 'info', 'application.orginvite');
            return $user;
        } catch (Throwable $e) {
            $transaction->rollback();
            Yii::log('Org invite accept failed: ' . $e->getMessage(), 'error', 'application.orginvite');
            throw $e;
        }
    }

    /**
     * Username derived from the email local part; usernames must be unique so
     * a numeric suffix is appended on collision. Mirrors OrgSignup::deriveUsername().
     */
    private function deriveUsername(string $email): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9_.]/', '', strstr($email, '@', true) ?: $email));
        $base = substr($base !== '' ? $base : 'user', 0, 50);
        $username = $base;
        $suffix = 1;
        while (User::model()->findByAttributes(['users_name' => $username]) !== null) {
            $username = substr($base, 0, 50 - strlen((string) $suffix)) . $suffix;
            $suffix++;
        }
        return $username;
    }

    /**
     * Grants the curated, hardcoded member permission set through the canonical
     * Permission::setPermissions() chokepoint, with the acting-session bypass (there is
     * no admin session mid-accept). setPermissions() structurally strips 'superadmin' for
     * any non-superadmin acting session — see OrgSignup::grantOrgAdminPermissions().
     */
    private function grantMemberPermissions(int $userId): void
    {
        Permission::setPermissions($userId, 0, 'global', self::MEMBER_PERMISSIONS, true);
    }

    /**
     * Best-effort invite email. A send failure is logged and returns false, but never throws
     * or rolls back the already-persisted invite row, so the invite stays usable (and the
     * caller's success message stays truthful about the DB state) even if SMTP is down.
     */
    private function sendInviteEmail(OrgInvite $invite): bool
    {
        $link = Yii::app()->createValidatedAbsoluteUrl('invite/accept', ['token' => $invite->token]);
        if ($link === false) {
            $link = Yii::app()->createUrl('invite/accept', ['token' => $invite->token]);
        }

        $org = Organization::model()->findByPk($invite->org_id);
        $orgName = $org !== null ? $org->name : Yii::app()->getConfig('sitename');

        $mailer = new LimeMailer();
        $mailer->addAddress($invite->email);
        $mailer->Subject = sprintf(gT('You have been invited to join %s'), $orgName);
        $mailer->setFrom(Yii::app()->getConfig('siteadminemail'), Yii::app()->getConfig('siteadminname'));
        $mailer->Body = nl2br(sprintf(
            gT("You have been invited to join '%s'. Click the link below to set your password and activate your account:\n\n%s\n\nThis invitation expires on %s (UTC)."),
            $orgName,
            $link,
            (string) $invite->expires
        ));
        $mailer->isHtml(true);
        $mailer->emailType = 'orginvite';

        if (!$mailer->sendMessage()) {
            Yii::log('Org invite email failed for invite ' . $invite->id . ': ' . $mailer->getError(), 'error', 'application.orginvite');
            return false;
        }
        return true;
    }
}
