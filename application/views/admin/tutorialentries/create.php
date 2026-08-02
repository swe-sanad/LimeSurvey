<?php
/* @var $this TutorialEntryController */
/* @var $model TutorialEntry */

$this->breadcrumbs = array(
    gT('Tutorial Entries') => array('index'),
    gT('Create'),
);

$this->menu = array(
    array('label' => gT('List TutorialEntry'), 'url' => array('index')),
    array('label' => gT('Manage TutorialEntry'), 'url' => array('admin')),
);
?>

<h1><?php echo gT('Create TutorialEntry'); ?></h1>

<?php $this->renderPartial('_form', array('model' => $model)); ?>