<?php

use LimeSurvey\Models\Services\OrgSignup;

/**
 * Public self-registration: an org name + an admin account, instant
 * activation, CAPTCHA-gated. See docs/multitenancy/PHASE1-DESIGN.md
 * Workflow A. Controller stays thin — all creation logic lives in
 * OrgSignup.
 */
class SignupController extends CController
{
    public $layout = 'signup';

    /** @var array<string,string> field => error message, set on a failed POST */
    private $errors = [];

    /** @var array<string,string> sticky field values to re-populate the form */
    private $values = [];

    public function init()
    {
        parent::init();
        // Defines getLanguageRTL(), used by the signup layout's <html dir> — same
        // helper LandingController loads; without it the layout fatals at render.
        App()->loadHelper('surveytranslator');
        App()->getClientScript()->registerPackage('bootstrap');
    }

    public function actions()
    {
        return [
            'captcha' => [
                'class' => 'CaptchaExtendedAction',
                'mode' => CaptchaExtendedAction::MODE_MATH,
            ],
        ];
    }

    public function actionIndex()
    {
        $this->values = [
            'org_name' => (string) App()->request->getPost('org_name', ''),
            'full_name' => (string) App()->request->getPost('full_name', ''),
            'email' => (string) App()->request->getPost('email', ''),
        ];

        if (App()->request->getIsPostRequest()) {
            $this->handleSubmit();
            return;
        }

        $this->render('index', [
            'errors' => $this->errors,
            'values' => $this->values,
        ]);
    }

    /**
     * Basic per-session throttle against scripted signup abuse: the CAPTCHA
     * already blocks bots, this bounds how many attempts one session may make
     * per minute regardless.
     */
    private function isRateLimited(): bool
    {
        $session = App()->session;
        $now = time();
        $windowStart = (int) ($session['signup_throttle_start'] ?? 0);
        $attempts = (int) ($session['signup_throttle_count'] ?? 0);
        if ($now - $windowStart > 60) {
            $session['signup_throttle_start'] = $now;
            $session['signup_throttle_count'] = 1;
            return false;
        }
        $session['signup_throttle_count'] = $attempts + 1;
        return $attempts >= 5;
    }

    private function handleSubmit(): void
    {
        if ($this->isRateLimited()) {
            $this->errors['_general'] = gT('Too many attempts. Please wait a minute and try again.');
            $this->render('index', ['errors' => $this->errors, 'values' => $this->values]);
            return;
        }

        $captcha = $this->createAction('captcha');
        if (!$captcha->validate((string) App()->request->getPost('loadsecurity', ''), false)) {
            $this->errors['captcha'] = gT('Your answer to the security question was not correct - please try again.');
            $this->render('index', ['errors' => $this->errors, 'values' => $this->values]);
            return;
        }

        $result = (new OrgSignup())->register([
            'org_name' => App()->request->getPost('org_name', ''),
            'full_name' => App()->request->getPost('full_name', ''),
            'email' => App()->request->getPost('email', ''),
            'password' => (string) App()->request->getPost('password', ''),
            'password_confirm' => (string) App()->request->getPost('password_confirm', ''),
        ]);

        if (!$result['success']) {
            $this->errors = $result['errors'];
            $this->render('index', ['errors' => $this->errors, 'values' => $this->values]);
            return;
        }

        Yii::log(
            'New org signup: user ' . $result['user']->uid . ' org ' . $result['user']->owner_org_id,
            'info',
            'application.signup'
        );

        if ($this->loginNewUser($result['user'])) {
            $this->redirect(App()->createUrl('admin/index'));
            return;
        }
        // The account exists but auto-login was refused (e.g. an IP lockout from
        // repeated attempts — authenticate() returns false at LSUserIdentity.php's
        // isLockedOut check). Don't dump the user on the admin dashboard, which
        // would silently bounce them to the login screen; send them there with an
        // explanation instead.
        App()->setFlashMessage(gT('Your account was created. Please log in to continue.'), 'success');
        $this->redirect(App()->createUrl('admin/authentication', ['sa' => 'login']));
    }

    /**
     * Logs the freshly created user in through the same identity flow as the
     * normal admin login (Authdb plugin against the password just set), so
     * session setup (loginID, language, CSRF regeneration, …) never diverges
     * from the standard login path.
     */
    private function loginNewUser(User $user): bool
    {
        $identity = new LSUserIdentity($user->users_name, App()->request->getPost('password', ''));
        $identity->setPlugin('Authdb');
        // authenticate() establishes the session itself (postLogin -> App()->user->login
        // + session['loginID']); it returns false if login is refused (e.g. IP lockout).
        return (bool) $identity->authenticate();
    }
}
