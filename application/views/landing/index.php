<?php
/**
 * @var string|null $siteAdminEmail
 */
$signupUrl = App()->createUrl('signup/index');
$loginUrl = App()->createUrl('admin/authentication', ['sa' => 'login']);
?>
<header class="border-bottom bg-light">
    <nav class="navbar navbar-expand container py-3" aria-label="<?php eT('Main navigation'); ?>">
        <a class="navbar-brand fw-bold fs-4" href="<?php echo App()->createUrl(''); ?>"><?php eT('Surveys'); ?></a>
        <div class="ms-auto">
            <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-secondary me-2"><?php eT('Log in'); ?></a>
            <a href="<?php echo $signupUrl; ?>" class="btn btn-primary"><?php eT('Create your free account'); ?></a>
        </div>
    </nav>
</header>

<main id="main-content">
    <section class="bg-light" aria-labelledby="hero-heading">
        <div class="container py-5 text-center">
            <h1 id="hero-heading" class="display-5 fw-bold mb-3"><?php eT('Surveys that stay yours'); ?></h1>
            <p class="lead text-muted col-lg-8 mx-auto mb-4">
                <?php eT('Create an isolated workspace for your team, build unlimited surveys, and collect responses — all under your own account, in minutes.'); ?>
            </p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                <a href="<?php echo $signupUrl; ?>" class="btn btn-primary btn-lg px-4"><?php eT('Create your free account'); ?></a>
                <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-secondary btn-lg px-4"><?php eT('Log in'); ?></a>
            </div>
        </div>
    </section>

    <section class="container py-5" aria-labelledby="features-heading">
        <h2 id="features-heading" class="text-center h3 mb-4"><?php eT('Everything your team needs'); ?></h2>
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?php eT('Your own workspace'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Every organization gets an isolated workspace — your surveys, users, and data are never visible to other organizations.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?php eT('Unlimited surveys'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Build as many surveys as you need with a flexible question and design toolkit.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?php eT('Invite your team'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Add teammates to your organization and manage who can create, edit, and view surveys.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?php eT('Own your data'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Your responses stay under your control, on infrastructure you can trust.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-light py-5" aria-labelledby="how-it-works-heading">
        <div class="container">
            <h2 id="how-it-works-heading" class="text-center h3 mb-4"><?php eT('How it works'); ?></h2>
            <div class="row g-4 text-center">
                <div class="col-12 col-md-4">
                    <div class="fs-2 fw-bold text-primary mb-2">1</div>
                    <h3 class="h6"><?php eT('Sign up'); ?></h3>
                    <p class="text-muted"><?php eT('Create your organization and account in under a minute.'); ?></p>
                </div>
                <div class="col-12 col-md-4">
                    <div class="fs-2 fw-bold text-primary mb-2">2</div>
                    <h3 class="h6"><?php eT('Create surveys'); ?></h3>
                    <p class="text-muted"><?php eT('Build your first survey with the question editor.'); ?></p>
                </div>
                <div class="col-12 col-md-4">
                    <div class="fs-2 fw-bold text-primary mb-2">3</div>
                    <h3 class="h6"><?php eT('Share & collect'); ?></h3>
                    <p class="text-muted"><?php eT('Share the link and watch responses come in.'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5 text-center" aria-labelledby="cta-heading">
        <h2 id="cta-heading" class="h3 mb-3"><?php eT('Ready to get started?'); ?></h2>
        <a href="<?php echo $signupUrl; ?>" class="btn btn-primary btn-lg px-4"><?php eT('Create your free account'); ?></a>
    </section>
</main>

<footer class="border-top py-4">
    <div class="container text-center text-muted small">
        <p class="mb-0">
            <?php eT('Contact'); ?>:
            <?php if (!empty($siteAdminEmail)): ?>
                <a href="mailto:<?php echo CHtml::encode($siteAdminEmail); ?>"><?php echo CHtml::encode($siteAdminEmail); ?></a>
            <?php else: ?>
                <?php eT('Not configured'); ?>
            <?php endif; ?>
        </p>
    </div>
</footer>
