<?php
/* @var $this SurveymenuController */
/* @var $model Surveymenu */

$this->breadcrumbs = array(
    gT('Surveymenus') => array('index'),
    gT('Create'),
);

$this->menu = array(
    array('label' => gT('List Surveymenu'), 'url' => array('index')),
    array('label' => gT('Manage Surveymenu'), 'url' => array('admin')),
);
?>

<h1><?php echo gT('Create Surveymenu'); ?></h1>

<?php $this->renderPartial('_form', array('model' => $model)); ?>