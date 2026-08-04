<?php
/**
 * Generic "invitation is invalid or expired" page. Rendered whenever the invite
 * token is missing/unknown/expired/already consumed/revoked, or accept() throws.
 * Deliberately carries NO data so it can never leak whether an email/token exists.
 */
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <h1 class="h3 mb-3"><?php eT('This invitation is invalid or has expired'); ?></h1>
                <p class="text-muted mb-4">
                    <?php eT('The invite link you followed is no longer valid. Ask whoever invited you to send a new invitation.'); ?>
                </p>
                <a href="<?php echo App()->createUrl('admin/authentication', ['sa' => 'login']); ?>" class="btn btn-primary">
                    <?php eT('Go to log in'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
