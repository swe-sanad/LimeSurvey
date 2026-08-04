<?php
/**
 * @var string|null $siteAdminEmail
 */
$signupUrl = App()->createUrl('signup/index');
$loginUrl = App()->createUrl('admin/authentication', ['sa' => 'login']);
?>
<a class="surveys-skip-link" href="#main-content"><?php eT('Skip to main content'); ?></a>

<header class="border-bottom bg-white">
    <nav class="navbar navbar-expand container py-3" aria-label="<?php eT('Main navigation'); ?>">
        <a class="navbar-brand fw-bold fs-4" href="<?php echo App()->createUrl(''); ?>"><?php eT('Surveys'); ?></a>
        <div class="ms-auto">
            <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-secondary me-2"><?php eT('Log in'); ?></a>
            <a href="<?php echo $signupUrl; ?>" class="btn btn-primary"><?php eT('Create your free account'); ?></a>
        </div>
    </nav>
</header>

<main id="main-content">
    <section class="surveys-hero" aria-labelledby="hero-heading">
        <div class="container py-5 py-md-6 text-center">
            <span class="badge rounded-pill text-bg-light border text-muted fw-normal px-3 py-2 mb-3">
                <?php eT('Self-hosted · Multi-tenant · Built for teams'); ?>
            </span>
            <h1 id="hero-heading" class="display-5 fw-bold mb-3"><?php eT('Surveys that stay yours'); ?></h1>
            <p class="lead text-muted col-12 col-lg-8 mx-auto mb-4">
                <?php eT('Create an isolated workspace for your team, build unlimited surveys, and collect responses — all under your own account, in minutes.'); ?>
            </p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                <a href="<?php echo $signupUrl; ?>" class="btn btn-primary btn-lg px-4"><?php eT('Create your free account'); ?></a>
                <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-secondary btn-lg px-4"><?php eT('Log in'); ?></a>
            </div>
            <p class="text-muted small mt-3 mb-0"><?php eT('Set up your workspace in minutes. No sales calls, no lock-in.'); ?></p>
        </div>
    </section>

    <section class="container py-5 py-md-6" aria-labelledby="features-heading">
        <h2 id="features-heading" class="text-center h3 mb-2"><?php eT('Everything your team needs'); ?></h2>
        <p class="text-center text-muted col-12 col-lg-6 mx-auto mb-4"><?php eT('One workspace, complete control — from your first survey to your whole team.'); ?></p>
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 surveys-feature-card shadow-sm">
                    <div class="card-body">
                        <span class="surveys-icon-badge mb-3" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M12 3l7 3v5c0 4.5-2.9 8.2-7 9.5-4.1-1.3-7-5-7-9.5V6l7-3z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </span>
                        <h3 class="h5"><?php eT('Your own workspace'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Every organization gets an isolated workspace — your surveys, users, and data are never visible to other organizations.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 surveys-feature-card shadow-sm">
                    <div class="card-body">
                        <span class="surveys-icon-badge mb-3" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M12 3l9 5-9 5-9-5 9-5z"/>
                                <path d="M3 13l9 5 9-5"/>
                                <path d="M3 17l9 5 9-5"/>
                            </svg>
                        </span>
                        <h3 class="h5"><?php eT('Unlimited surveys'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Build as many surveys as you need with a flexible question and design toolkit.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 surveys-feature-card shadow-sm">
                    <div class="card-body">
                        <span class="surveys-icon-badge mb-3" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <circle cx="9" cy="8" r="3"/>
                                <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
                                <path d="M18 9v4"/>
                                <path d="M16 11h4"/>
                            </svg>
                        </span>
                        <h3 class="h5"><?php eT('Invite your team'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Add teammates to your organization and manage who can create, edit, and view surveys.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 surveys-feature-card shadow-sm">
                    <div class="card-body">
                        <span class="surveys-icon-badge mb-3" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <ellipse cx="12" cy="5" rx="7" ry="3"/>
                                <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"/>
                                <path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>
                            </svg>
                        </span>
                        <h3 class="h5"><?php eT('Own your data'); ?></h3>
                        <p class="card-text text-muted"><?php eT('Your responses stay under your control, on infrastructure you can trust.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-light py-5 py-md-6" aria-labelledby="how-it-works-heading">
        <div class="container">
            <h2 id="how-it-works-heading" class="text-center h3 mb-4"><?php eT('How it works'); ?></h2>
            <div class="row g-4 text-center">
                <div class="col-12 col-md-4 surveys-step">
                    <div class="surveys-step-badge mx-auto mb-3">1</div>
                    <h3 class="h6"><?php eT('Sign up'); ?></h3>
                    <p class="text-muted"><?php eT('Create your organization and account in under a minute.'); ?></p>
                </div>
                <div class="col-12 col-md-4 surveys-step">
                    <div class="surveys-step-badge mx-auto mb-3">2</div>
                    <h3 class="h6"><?php eT('Create surveys'); ?></h3>
                    <p class="text-muted"><?php eT('Build your first survey with the question editor.'); ?></p>
                </div>
                <div class="col-12 col-md-4 surveys-step">
                    <div class="surveys-step-badge mx-auto mb-3">3</div>
                    <h3 class="h6"><?php eT('Share & collect'); ?></h3>
                    <p class="text-muted"><?php eT('Share the link and watch responses come in.'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-4 text-center">
        <p class="text-muted mb-0">
            <?php eT('Built on a proven open-source foundation, run on infrastructure you control — no vendor lock-in, ever.'); ?>
        </p>
    </section>

    <section class="container py-5 py-md-6" aria-labelledby="cta-heading">
        <div class="surveys-cta-band text-center px-4 py-5">
            <h2 id="cta-heading" class="h3 mb-2"><?php eT('Ready to get started?'); ?></h2>
            <p class="mb-4 col-12 col-lg-6 mx-auto" style="color: rgba(255,255,255,.85);">
                <?php eT('Create your organization, invite your team, and start collecting responses today.'); ?>
            </p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                <a href="<?php echo $signupUrl; ?>" class="btn btn-light btn-lg px-4 fw-semibold"><?php eT('Create your free account'); ?></a>
                <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-light btn-lg px-4"><?php eT('Log in'); ?></a>
            </div>
        </div>
    </section>
</main>

<footer class="border-top py-5 bg-white">
    <div class="container">
        <div class="row gy-3 align-items-center">
            <div class="col-12 col-md-6">
                <div class="fw-bold fs-5 mb-1"><?php eT('Surveys'); ?></div>
                <p class="text-muted small mb-0"><?php eT('Build and share surveys, your way.'); ?></p>
            </div>
            <div class="col-12 col-md-6 text-md-end">
                <p class="mb-0 small">
                    <span class="text-muted"><?php eT('Contact'); ?>:</span>
                    <?php if (!empty($siteAdminEmail)): ?>
                        <a href="mailto:<?php echo CHtml::encode($siteAdminEmail); ?>"><?php echo CHtml::encode($siteAdminEmail); ?></a>
                    <?php else: ?>
                        <span class="text-muted"><?php eT('Not configured'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <hr class="my-4"/>
        <p class="text-muted small text-center mb-0">&copy; <?php echo date('Y'); ?> <?php eT('Surveys'); ?>. <?php eT('All rights reserved.'); ?></p>
    </div>
</footer>
