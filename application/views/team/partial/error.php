<?php

/**
 * Subview: Error message in the team panel. Mirrors
 * application/views/userManagement/partial/error.php exactly — TeamController
 * renders 'partial/error' the same way UserManagementController does.
 *
 * @var string[] $errors
 * @var bool|null $noButton
 */

?>
<div class="modal-header">
    <h5 class="modal-title"><?= gT('Error') ?></h5>
</div>
<div class="modal-body">
    <div class="row selector--animated_row">
        <div class="col-12 text-center">
            <div class="cross_mark">
                <div class="sa-icon sa-error animate">
                    <span class="sa-line sa-tip animateerrorTip"></span>
                    <span class="sa-line sa-long animateerrorLong"></span>
                    <div class="sa-placeholder"></div>
                    <div class="sa-fix"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row ls-space margin top-15 bottom-15">
        <div class="col-12">
            <?php foreach ($errors as $error) {
                echo "<pre>" . CHtml::encode($error) . "</pre>";
            }
            ?>
        </div>
    </div>
</div>
<div class="modal-footer">
    <?php if (!isset($noButton)) : ?>
        <button id="exitForm" class="btn btn-cancel" data-bs-dismiss="modal">
            <?= gT('Close') ?>
        </button>
    <?php endif; ?>
</div>
