<?php

namespace ls\tests;

/**
 * Public marketing landing page (Phase 1 multi-tenancy, Workflow A stage 3).
 * Value here is "the route resolves and renders" — the isolation + signup
 * behavior is covered by IsolationTest/OrgSignupTest; this page is static
 * marketing markup, not business logic.
 *
 * NOTE: requires the Dockerized PHP 8.1 + DB test env (tests/README.md), same
 * as IsolationTest — cannot run from this environment (no Docker daemon,
 * local PHP is 8.4).
 */
class LandingControllerTest extends TestBaseClass
{
    public function testIndexRendersTheLandingPage()
    {
        $controller = new \LandingController('landing');

        ob_start();
        $controller->actionIndex();
        $output = ob_get_clean();

        $this->assertStringContainsString('Surveys', $output);
        $this->assertStringContainsString(
            \Yii::app()->createUrl('signup/index'),
            $output,
            'CTA must link to the signup route'
        );
    }

    public function testRouteMapsRootToLandingIndex()
    {
        $routes = require \Yii::getPathOfAlias('application') . '/config/routes.php';

        $this->assertArrayHasKey('', $routes);
        $this->assertEquals('landing/index', $routes['']);
    }
}
