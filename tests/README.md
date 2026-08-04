Tests are divided into two folders:

    unit/
    functional/

Unit tests are tests that NOT use the Facebook webdriver or headless browser. Functional tests are tests that do.

Functional tests are divided into back-end (survey administration) and front-end (survey taking) tests.

Unit tests are divided into models, helpers, controllers, etc...

## Running the tests locally

Install the dev dependencies first (PHPUnit, Psalm, PHPMD, php-webdriver, ...):

    composer install

**Static gate — no database or browser needed.** `composer test` runs the lint
+ static-analysis checks (they do not boot the app or touch a DB), so it is the
fastest signal and safe to run anywhere:

    composer test

It runs `tests/bin/lint-tests`, `tests/bin/lint-application`,
`tests/bin/lint-twig-translations` (fails if any `gT()/eT()/ngT()` string
literal in a `.twig` file is not covered by a `locale/_template/limesurvey.pot`
msgid or the `application/helpers/twig_translation_helper.php` harvest — the
extraction bot does not scan twig), plus PHPMD and Psalm.

**PHPUnit unit + functional suites — need a configured test database.** They
bootstrap the application, so they expect a working DB connection. Run the whole
suite (with coverage text) or a single suite/file:

    composer phpunit
    ./vendor/bin/phpunit --testsuite unit
    ./vendor/bin/phpunit tests/unit/helpers/SomeTest.php

**Functional / acceptance suites additionally need a running LimeSurvey
instance and a Selenium/WebDriver server** — they drive a real browser via
`php-webdriver`. The reference environment is the Apache-based CI setup under
`tests/CI-pipeline/github-actions-apache/`; mirror it for a working local box.

### Coverage

Coverage is configured in `phpunit.xml` for `application/models/services` only
(the unit-tested services layer), so the `--coverage-text` number reflects that
scope, not the whole codebase — widen the `<include>` there only alongside new
tests, or the percentage just drops.

## Debugging

Often, failures happen due to timing issue, or missing wait() or sleep().

Here's one way to debug the CI:

    try {
        $web->wait(20)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('#massive-actions-modal-failedemail-grid-resend-1 #preserveResend')));
    } catch (TimeoutException $ex) {
        $body = $web->findElement(WebDriverBy::tagName('body'));
        var_dump($body->getText());
        throw $ex;
    }


## Future directions

Something to consider for the future is the time-constraint of the CI. Integrity tests take longer to execute,
especially when scripting the browser. They are also hard to setup and run locally.

Future folder structure:

* unit/ - for unit-tests, using mocks, not depending on database except for AR cache setup
* functional/ - tests interacting with database and filesystem
* integrity/ - tests scripting the browser

They should be run in that order, since unit tests are faster than functional tests, which are faster than
integrity tests.

Unit tests should be able to run in parallel.
