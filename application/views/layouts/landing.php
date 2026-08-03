<!doctype html>
<html lang="<?php echo App()->language; ?>" dir="<?php echo getLanguageRTL(App()->language) ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php eT('Surveys'); ?> — <?php eT('Build and share surveys, your way'); ?></title>
    <link rel="icon" href="<?php echo Yii::app()->baseUrl; ?>/images/favicon.ico"/>
</head>

<body>
<?php echo $content; ?>
</body>
</html>
