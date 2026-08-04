<?php
/* @var $this TutorialsController */
/* @var $model Tutorial */

$this->breadcrumbs = array(
    gT('Tutorial') => array('index'),
    $model->name,
);

$this->menu = array(
    array('label' => gT('List Tutorial'), 'url' => array('index')),
    array('label' => gT('Create Tutorial'), 'url' => array('create')),
    array('label' => gT('Update Tutorial'), 'url' => array('update', 'id' => $model->tid)),
    array('label' => gT('Delete Tutorial'), 'url' => '#', 'linkOptions' => array('submit' => array('delete','id' => $model->tid),'confirm' => gT('Are you sure you want to delete this item?'))),
    array('label' => gT('Manage Tutorial'), 'url' => array('admin')),
);
?>

<h1><?php echo sprintf(gT('View Tutorial #%s'), $model->tid); ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array(
        'tid',
        'name',
        'description',
        'active',
        'permission',
        'permission_grade',
    ),
)); ?>
