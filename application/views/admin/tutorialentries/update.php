<?php
/* @var $this TutorialEntryController */
/* @var $model TutorialEntry */

$this->breadcrumbs = array(
    gT('Tutorial Entries') => array('index'),
    $model->title => array('view','id' => $model->teid),
    gT('Update'),
);

$this->menu = array(
    array('label' => gT('List TutorialEntry'), 'url' => array('index')),
    array('label' => gT('Create TutorialEntry'), 'url' => array('create')),
    array('label' => gT('View TutorialEntry'), 'url' => array('view', 'id' => $model->teid)),
    array('label' => gT('Manage TutorialEntry'), 'url' => array('admin')),
);
?>

<h1><?php echo sprintf(gT('Update TutorialEntry %s'), $model->teid); ?></h1>

<?php $this->renderPartial('_form', array('model' => $model)); ?>