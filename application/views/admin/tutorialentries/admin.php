<?php
/* @var $this TutorialEntryController */
/* @var $model TutorialEntry */

$this->breadcrumbs = array(
    gT('Tutorial Entries') => array('index'),
    gT('Manage'),
);

$this->menu = array(
    array('label' => gT('List TutorialEntry'), 'url' => array('index')),
    array('label' => gT('Create TutorialEntry'), 'url' => array('create')),
);

Yii::app()->clientScript->registerScript('search', "
$('.search-button').click(function(){
	$('.search-form').toggle();
	return false;
});
$('.search-form form').submit(function(){
	$('#tutorial-entry-grid').yiiGridView('update', {
		data: $(this).serialize()
	});
	return false;
});
");
?>

<h1><?php echo gT('Manage Tutorial Entries'); ?></h1>

<p>
<?php echo gT('You may optionally enter a comparison operator (<b>&lt;</b>, <b>&lt;=</b>, <b>&gt;</b>, <b>&gt;=</b>, <b>&lt;&gt;</b> or <b>=</b>) at the beginning of each of your search values to specify how the comparison should be done.', 'unescaped'); ?>
</p>

<?php echo CHtml::link(gT('Advanced Search'), '#', array('class' => 'search-button')); ?>
<div class="search-form" style="display:none">
<?php $this->renderPartial('_search', array(
    'model' => $model,
)); ?>
</div><!-- search-form -->

<?php $this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'tutorial-entry-grid',
    'dataProvider' => $model->search(),
    'filter' => $model,
    'columns' => array(
        'teid',
        'tid',
        'title',
        'content',
        'settings',
        array(
            'class' => 'CButtonColumn',
        ),
    ),
)); ?>
