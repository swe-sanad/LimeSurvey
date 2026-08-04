<?php
/* @var $this TutorialEntryController */
/* @var $model TutorialEntry */

$this->breadcrumbs = array(
    gT('Tutorial Entries') => array('index'),
    $model->title,
);

$this->menu = array(
    array('label' => gT('List TutorialEntry'), 'url' => array('index')),
    array('label' => gT('Create TutorialEntry'), 'url' => array('create')),
    array('label' => gT('Update TutorialEntry'), 'url' => array('update', 'id' => $model->teid)),
    array('label' => gT('Delete TutorialEntry'), 'url' => '#', 'linkOptions' => array('submit' => array('delete','id' => $model->teid),'confirm' => gT('Are you sure you want to delete this item?'))),
    array('label' => gT('Manage TutorialEntry'), 'url' => array('admin')),
);
?>

<h1><?php echo sprintf(gT('View TutorialEntry #%s'), $model->teid); ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array(
        'teid',
        'tid',
        'title',
        'content',
        'settings',
    ),
)); ?>
