<?php

/**
 * Public marketing landing page (Phase 1 multi-tenancy, Workflow A stage 3).
 * Replaces the default public survey list at the site root. Neutral
 * "Surveys" branding — installer-pattern controller (plain PHP view, no
 * survey-theme Twig sandbox). See docs/multitenancy/PHASE1-DESIGN.md.
 */
class LandingController extends CController
{
    public $layout = 'landing';

    public function init()
    {
        parent::init();
        App()->loadHelper('surveytranslator');
        App()->getClientScript()->registerPackage('bootstrap');
    }

    public function actionIndex()
    {
        $this->render('index', [
            'siteAdminEmail' => App()->getConfig('siteadminemail'),
        ]);
    }
}
