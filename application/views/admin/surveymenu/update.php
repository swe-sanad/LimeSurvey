<?php
/* @var $this SurveymenuController */
/* @var $model Surveymenu */

$this->breadcrumbs = array(
    gT('Surveymenus') => array('index'),
    $model->title => array('view','id' => $model->id),
    gT('Update'),
);

$this->menu = array(
    array('label' => gT('List Surveymenu'), 'url' => array('index')),
    array('label' => gT('Create Surveymenu'), 'url' => array('create')),
    array('label' => gT('View Surveymenu'), 'url' => array('view', 'id' => $model->id)),
    array('label' => gT('Manage Surveymenu'), 'url' => array('admin')),
);
?>

<h1><?php echo sprintf(gT('Update Surveymenu %s'), $model->id); ?></h1>

<?php $this->renderPartial('_form', array('model' => $model)); ?>