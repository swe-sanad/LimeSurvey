<?php
/* @var $this TutorialsController */
/* @var $model Tutorial */

?>

<div class="container">
    <h1 class="pagetitle"><?php echo sprintf(gT('Update Tutorial %s'), $model->tid); ?></h1>
    <?php $this->renderPartial('/admin/tutorials/_form', array('model' => $model)); ?>
</div>