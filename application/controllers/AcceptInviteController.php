<?php

use LimeSurvey\Models\Services\OrgInviteService;

/**
 * Public invite-acceptance page (Phase 1 multi-tenancy, Workflow C): a token from
 * a team-invite email; the invitee sets a password and becomes a member User of
 * the inviting org. PUBLIC controller — mirrors SignupController exactly (no
 * accessRules => anonymous by construction, extends CController not LSBaseController).
 *
 * Never reveals whether a token/email is valid beyond the single generic
 * "invalid or expired" message — no timing/message differences.
 */
class AcceptInviteController extends CController
{
    public $layout = 'signup';

    /** @var array<string,string> field => error message, set on a failed POST */
    private $errors = [];

    public function init()
    {
        parent::init();
        // Defines getLanguageRTL(), used by the signup layout's <html dir> — same
        // helper SignupController loads; without it the layout fatals at render.
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
        $token = (string) App()->request->getParam('token', '');
        $invite = $token !== '' ? OrgInvite::findByToken($token) : null;

        if ($invite === null || !$invite->isValid()) {
            $this->render('invalid');
            return;
        }

        if (App()->request->getIsPostRequest()) {
            $this->handleSubmit($token);
            return;
        }

        $this->render('index', [
            'token' => $token,
            'errors' => $this->errors,
        ]);
    }

    /**
     * Basic per-session throttle against scripted acceptance abuse, same shape as
     * SignupController::isRateLimited() (separate session keys so the two never collide).
     */
    private function isRateLimited(): bool
    {
        $session = App()->session;
        $now = time();
        $windowStart = (int) ($session['invite_throttle_start'] ?? 0);
        $attempts = (int) ($session['invite_throttle_count'] ?? 0);
        if ($now - $windowStart > 60) {
            $session['invite_throttle_start'] = $now;
            $session['invite_throttle_count'] = 1;
            return false;
        }
        $session['invite_throttle_count'] = $attempts + 1;
        return $attempts >= 5;
    }

    private function handleSubmit(string $token): void
    {
        if ($this->isRateLimited()) {
            $this->errors['_general'] = gT('Too many attempts. Please wait a minute and try again.');
            $this->render('index', ['token' => $token, 'errors' => $this->errors]);
            return;
        }

        $captcha = $this->createAction('captcha');
        if (!$captcha->validate((string) App()->request->getPost('loadsecurity', ''), false)) {
            $this->errors['captcha'] = gT('Your answer to the security question was not correct - please try again.');
            $this->render('index', ['token' => $token, 'errors' => $this->errors]);
            return;
        }

        $password = (string) App()->request->getPost('password', '');
        $confirm = (string) App()->request->getPost('password_confirm', '');
        $fullName = (string) App()->request->getPost('full_name', '');

        $strengthError = (new User())->checkPasswordStrength($password);
        if ($strengthError !== '') {
            $this->errors['password'] = $strengthError;
        } elseif ($password !== $confirm) {
            $this->errors['password_confirm'] = gT('Passwords do not match.');
        }

        if (!empty($this->errors)) {
            $this->render('index', ['token' => $token, 'errors' => $this->errors]);
            return;
        }

        // Re-load + re-check right before writing: the invite may have expired or been
        // consumed/revoked between the GET that rendered the form and this POST.
        $invite = OrgInvite::findByToken($token);
        if ($invite === null || !$invite->isValid()) {
            $this->render('invalid');
            return;
        }

        try {
            $user = (new OrgInviteService())->accept($token, $password, $fullName);
        } catch (Throwable $e) {
            Yii::log('Invite accept failed for token lookup: ' . $e->getMessage(), 'error', 'application.orginvite');
            $this->render('invalid');
            return;
        }

        Yii::log(
            'Org invite accepted via public flow: user ' . $user->uid . ' org ' . $user->owner_org_id,
            'info',
            'application.orginvite'
        );

        if ($this->loginNewUser($user)) {
            $this->redirect(App()->createUrl('admin/index'));
            return;
        }
        // The account exists but auto-login was refused (e.g. an IP lockout) — send the
        // user to the login screen instead of silently bouncing them off the dashboard.
        App()->setFlashMessage(gT('Your account was created. Please log in to continue.'), 'success');
        $this->redirect(App()->createUrl('admin/authentication', ['sa' => 'login']));
    }

    /**
     * Logs the freshly created user in through the same identity flow as the normal
     * admin login — mirrors SignupController::loginNewUser() exactly.
     */
    private function loginNewUser(User $user): bool
    {
        $identity = new LSUserIdentity($user->users_name, App()->request->getPost('password', ''));
        $identity->setPlugin('Authdb');
        return (bool) $identity->authenticate();
    }
}
