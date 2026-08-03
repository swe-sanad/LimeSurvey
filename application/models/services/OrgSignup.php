<?php

namespace LimeSurvey\Models\Services;

use CDbTransaction;
use Organization;
use Permission;
use User;
use Yii;

/**
 * Self-service org signup: creates an isolated Organization + an active
 * org-admin User in one transaction, then grants that user the CURATED
 * org-admin global permission set.
 *
 * NEVER grants 'superadmin' — that would null the org in TenantContext and
 * bypass tenant scoping for every read. See docs/multitenancy/PHASE1-DESIGN.md
 * and the isolation invariant in application/core/TenantContext.php.
 */
class OrgSignup
{
    /** Permission keys granted to every new org-admin (never 'superadmin'). */
    private const ORG_ADMIN_PERMISSIONS = ['surveys', 'users', 'usergroups', 'templates', 'labelsets'];

    /**
     * @param array $input Expected keys: org_name, full_name, email, password, password_confirm.
     * @return array{success:bool,user?:User,errors?:array<string,string>}
     */
    public function register(array $input): array
    {
        $errors = $this->validate($input);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        /** @var CDbTransaction $transaction */
        $transaction = Yii::app()->db->beginTransaction();
        try {
            $org = new Organization();
            $org->name = trim($input['org_name']);
            $org->slug = $this->uniqueSlug($org->name);
            $org->status = 'active';
            if (!$org->save()) {
                throw new \Exception('Could not save organization: ' . print_r($org->errors, true));
            }

            $user = new User();
            $user->users_name = $this->deriveUsername($input['email']);
            $user->setPassword($input['password']);
            $user->full_name = trim($input['full_name']);
            $user->email = trim($input['email']);
            $user->parent_id = 0;
            $user->lang = 'auto';
            $user->created = gmdate('Y-m-d H:i:s');
            $user->modified = gmdate('Y-m-d H:i:s');
            $user->user_status = true;
            $user->owner_org_id = $org->org_id;
            if (!$user->save()) {
                throw new \Exception('Could not save user: ' . print_r($user->errors, true));
            }
            // The creator of an org is its own parent (no external owner).
            $user->parent_id = $user->uid;
            if (!$user->save(false, ['parent_id'])) {
                throw new \Exception('Could not set user parent_id: ' . print_r($user->errors, true));
            }

            $this->grantOrgAdminPermissions((int) $user->uid);

            $transaction->commit();
            return ['success' => true, 'user' => $user];
        } catch (\Throwable $e) {
            $transaction->rollback();
            Yii::log('Org signup failed: ' . $e->getMessage(), 'error', 'application.signup');
            return ['success' => false, 'errors' => ['_general' => gT('Could not complete signup. Please try again.')]];
        }
    }

    /**
     * @return array<string,string> field => error message
     */
    private function validate(array $input): array
    {
        $errors = [];
        $orgName = trim((string) ($input['org_name'] ?? ''));
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');

        if ($orgName === '') {
            $errors['org_name'] = gT('Organization name is required.');
        } elseif (mb_strlen($orgName) > 200) {
            $errors['org_name'] = gT('Organization name is too long (200 characters maximum).');
        }
        if ($fullName === '') {
            $errors['full_name'] = gT('Full name is required.');
        } elseif (mb_strlen($fullName) > 50) {
            $errors['full_name'] = gT('Full name is too long (50 characters maximum).');
        }
        if ($email === '') {
            $errors['email'] = gT('Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = gT('Please enter a valid email address.');
        } elseif (User::model()->findByAttributes(['email' => $email]) !== null) {
            $errors['email'] = gT('This email address is already registered.');
        }
        if ($password === '') {
            $errors['password'] = gT('Password is required.');
        } elseif (($strengthError = (new User())->checkPasswordStrength($password))) {
            $errors['password'] = $strengthError;
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = gT('Passwords do not match.');
        }

        return $errors;
    }

    /**
     * Username derived from the email local part; usernames must be unique so
     * a numeric suffix is appended on collision.
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

    private function uniqueSlug(string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $base = $base !== '' ? $base : 'org';
        $slug = $base;
        $suffix = 1;
        while (Organization::model()->findByAttributes(['slug' => $slug]) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    /**
     * Grants the curated org-admin global permission set through the canonical
     * Permission::setPermissions() chokepoint, with the acting-session bypass
     * (there is no admin session at signup). setPermissions() structurally
     * strips 'superadmin' for any non-superadmin acting session, so an org-admin
     * can never be granted it — a stronger guarantee than hand-built rows.
     */
    private function grantOrgAdminPermissions(int $userId): void
    {
        $permissions = [];
        foreach (self::ORG_ADMIN_PERMISSIONS as $permissionName) {
            $permissions[$permissionName] = [
                'create' => true, 'read' => true, 'update' => true,
                'delete' => true, 'import' => false, 'export' => true,
            ];
        }
        Permission::setPermissions($userId, 0, 'global', $permissions, true);
    }
}
