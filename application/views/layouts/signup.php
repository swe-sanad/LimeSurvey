<!doctype html>
<html lang="<?php echo App()->language; ?>" dir="<?php echo getLanguageRTL(App()->language) ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo gT('Sign up'); ?> — <?php eT('Surveys'); ?></title>
    <link rel="icon" href="<?php echo Yii::app()->baseUrl; ?>/images/favicon.ico"/>
</head>

<body class="bg-light">
<main class="container py-5">
    <?php echo $content; ?>
</main>
</body>
</html>
