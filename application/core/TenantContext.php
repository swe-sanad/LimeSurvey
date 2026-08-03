<?php

/**
 * Resolves the "current organization" for the request.
 *
 * The sole source of truth for tenant resolution — mirrors Permission's own
 * current-user resolution (App()->getCurrentUserId(), backed by
 * Yii::app()->session['loginID']) so the two can never diverge.
 *
 * @see docs/multitenancy/PLAN.md Phase 0, task 0.5
 */
class TenantContext
{
    /**
     * The logged-in user's owner_org_id, or null for a guest / a platform
     * super-admin (who is not scoped to any single org).
     *
     * @return int|null
     */
    public static function currentOrgId()
    {
        $uid = App()->getCurrentUserId();
        if (empty($uid)) {
            return null;
        }
        if (Permission::model()->hasGlobalPermission('superadmin', 'read', $uid)) {
            return null;
        }
        $user = User::model()->findByPk($uid);
        if ($user === null || empty($user->owner_org_id)) {
            return null;
        }
        return (int) $user->owner_org_id;
    }
}
