<!doctype html>
<html lang="<?php echo App()->language; ?>" dir="<?php echo getLanguageRTL(App()->language) ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php eT('Surveys'); ?> — <?php eT('Build and share surveys, your way'); ?></title>
    <link rel="icon" href="<?php echo Yii::app()->baseUrl; ?>/images/favicon.ico"/>
    <style>
        :root {
            --surveys-accent: var(--bs-primary, #0d6efd);
            --surveys-accent-dark: #0a4fb5;
            --surveys-accent-soft: #eef3ff;
            --surveys-ink: #1a1f36;
            --surveys-muted: #5b6472;
            --surveys-radius: .75rem;
            --surveys-shadow: 0 8px 24px -12px rgba(20, 24, 40, .18);
        }

        body {
            color: var(--surveys-ink);
        }

        .surveys-skip-link {
            position: absolute;
            inset-inline-start: -9999px;
            top: 0;
            z-index: 2000;
            background: #fff;
            color: var(--surveys-ink);
            padding: .75rem 1.25rem;
            border-radius: 0 0 .5rem 0;
            box-shadow: var(--surveys-shadow);
        }

        .surveys-skip-link:focus {
            inset-inline-start: 0;
        }

        a,
        button {
            transition: color .15s ease, background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        :focus-visible {
            outline: 3px solid var(--surveys-accent);
            outline-offset: 2px;
        }

        .surveys-hero {
            background: linear-gradient(180deg, var(--surveys-accent-soft) 0%, #fff 100%);
        }

        .surveys-icon-badge {
            width: 3rem;
            height: 3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--surveys-radius);
            background: var(--surveys-accent-soft);
            color: var(--surveys-accent);
        }

        .surveys-feature-card {
            border: 1px solid rgba(20, 24, 40, .08);
            border-radius: var(--surveys-radius);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .surveys-feature-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--surveys-shadow);
        }

        .surveys-step {
            position: relative;
        }

        .surveys-step-badge {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--surveys-accent);
            color: #fff;
            font-weight: 700;
        }

        .surveys-cta-band {
            background: linear-gradient(135deg, var(--surveys-accent) 0%, var(--surveys-accent-dark) 100%);
            color: #fff;
            border-radius: var(--surveys-radius);
        }

        .btn:hover {
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
<?php echo $content; ?>
</body>
</html>
