<?php
/**
 * Team page: current org's members + pending invites + invite-by-email form.
 *
 * @var $this TeamController
 * @var User $model User('search'), already org-scoped via User::search()
 * @var OrgInvite[] $invites pending invites for the org
 * @var bool $canInvite hasGlobalPermission('users','create')
 * @var bool $canManage hasGlobalPermission('users','update')
 */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('teamIndex');

$members = $model->search()->getData();
$currentUid = (int) App()->user->getId();
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4"><?php eT('Team'); ?></h1>

        <?php if ($canInvite): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php eT('Invite teammate'); ?></h2>
                    <?php echo CHtml::beginForm($this->createUrl('team/invite'), 'post', ['class' => 'row g-2 align-items-end']); ?>
                    <div class="col-auto flex-grow-1">
                        <label for="invite-email" class="form-label"><?php eT('Email address'); ?></label>
                        <input type="email" class="form-control" id="invite-email" name="email" required autocomplete="off"/>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><?php eT('Send invite'); ?></button>
                    </div>
                    <?php echo CHtml::endForm(); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php eT('Members'); ?></h2>
                <?php if (empty($members)): ?>
                    <p class="text-muted mb-0"><?php eT('No team members yet.'); ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php eT('Name'); ?></th>
                                <th scope="col"><?php eT('Email'); ?></th>
                                <th scope="col"><?php eT('Status'); ?></th>
                                <?php if ($canManage): ?>
                                    <th scope="col"><?php eT('Action'); ?></th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members as $member): ?>
                                <?php
                                $isSelf = ((int) $member->uid) === $currentUid;
                                $isSuperadmin = Permission::model()->hasGlobalPermission('superadmin', 'read', (int) $member->uid);
                                ?>
                                <tr>
                                    <td><?php echo CHtml::encode($member->full_name); ?></td>
                                    <td><?php echo CHtml::encode($member->email); ?></td>
                                    <td>
                                        <?php if ($member->user_status): ?>
                                            <span class="badge text-bg-success"><?php eT('Active'); ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary"><?php eT('Deactivated'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($canManage): ?>
                                        <td>
                                            <?php if ($member->user_status && !$isSelf && !$isSuperadmin): ?>
                                                <?php echo CHtml::beginForm($this->createUrl('team/deactivate'), 'post', ['class' => 'd-inline']); ?>
                                                <input type="hidden" name="uid" value="<?php echo (int) $member->uid; ?>"/>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php eT('Deactivate'); ?></button>
                                                <?php echo CHtml::endForm(); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-3"><?php eT('Pending invites'); ?></h2>
                <?php if (empty($invites)): ?>
                    <p class="text-muted mb-0"><?php eT('No pending invites.'); ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php eT('Email'); ?></th>
                                <th scope="col"><?php eT('Invited'); ?></th>
                                <th scope="col"><?php eT('Expires'); ?></th>
                                <?php if ($canManage): ?>
                                    <th scope="col"><?php eT('Action'); ?></th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invites as $invite): ?>
                                <tr>
                                    <td><?php echo CHtml::encode($invite->email); ?></td>
                                    <td><?php echo CHtml::encode($invite->created); ?></td>
                                    <td><?php echo CHtml::encode($invite->expires); ?></td>
                                    <?php if ($canManage): ?>
                                        <td>
                                            <?php echo CHtml::beginForm($this->createUrl('team/revokeInvite'), 'post', ['class' => 'd-inline']); ?>
                                            <input type="hidden" name="id" value="<?php echo (int) $invite->id; ?>"/>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?php eT('Revoke'); ?></button>
                                            <?php echo CHtml::endForm(); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
