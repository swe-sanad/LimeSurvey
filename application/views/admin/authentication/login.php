<?php

/**
 * Login form — reskinned to match the neutral "Surveys" signup/landing brand (Phase 1) so the
 * public auth funnel (landing -> signup -> log in) reads as one product. Only the PRESENTATION
 * changed: the form still posts to admin/authentication/sa/login, the username/password fields
 * are still the Authdb plugin's rendered block, and LimeSurvey's real auth flow (lockout, plugin
 * events, session, failure re-render) is untouched.
 */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('login');

// Surface a failed-login message (Authentication::actionLogin sets flash 'error') inside the card.
$loginError = App()->user->getFlash('error', '', false);

$pluginNames = array_keys($pluginContent);
if (!isset($defaultAuth)) {
    $defaultAuth = reset($pluginNames);
}
$multiAuth = count($pluginContent) > 1;
if ($multiAuth) {
    $selectedAuth = App()->getRequest()->getParam('authMethod', $defaultAuth);
    if (!in_array($selectedAuth, $pluginNames)) {
        $selectedAuth = $defaultAuth;
    }
    $possibleAuthMethods = [];
    foreach ($pluginNames as $plugin) {
        $info = App()->getPluginManager()->getPluginInfo($plugin);
        $methodName = call_user_func([$info['pluginClass'], 'getAuthMethodName']);
        $possibleAuthMethods[$plugin] = !empty($methodName) ? $methodName : $info['pluginName'];
    }
} else {
    $selectedAuth = $defaultAuth;
}

// Language list (same data the stock view built), rendered as a plain Bootstrap select.
$aLangList = getLanguageDataRestricted(true);
$languageData = [];
$reqLang = \LSYii_Validators::languageCodeFilter(App()->request->getParam('lang'));
if (!isset($aLangList[$reqLang]) || $reqLang === '') {
    $languageData['default'] = gT('Default');
} else {
    $languageData[$reqLang] = html_entity_decode((string) $aLangList[$reqLang]['nativedescription'], ENT_NOQUOTES, 'UTF-8') . " - " . $aLangList[$reqLang]['description'];
    $languageData['default'] = gT('Default');
    unset($aLangList[$reqLang]);
}
foreach ($aLangList as $sLangKey => $aLanguage) {
    $languageData[$sLangKey] = html_entity_decode((string) $aLanguage['nativedescription'], ENT_NOQUOTES, 'UTF-8') . " - " . $aLanguage['description'];
}
$forgotEnabled = Yii::app()->getConfig("display_user_password_in_email") === true;
$demoPrefill = Yii::app()->getConfig("demoMode") === true && Yii::app()->getConfig("demoModePrefill") === true;
?>
<noscript>
    <div class="alert alert-warning" role="alert"><?php eT('LimeSurvey does not work without JavaScript being activated in your browser.'); ?></div>
</noscript>

<div class="surveys-auth">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1"><?php eT('Log in'); ?></h1>
                    <p class="text-muted mb-4"><?php eT('Sign in to your organization workspace.'); ?></p>

                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $loginError; ?></div>
                    <?php endif; ?>
                    <?php if ($demoPrefill): ?>
                        <div class="alert alert-info" role="alert"><?php eT("Demo mode: login credentials are prefilled — just select Log in."); ?></div>
                    <?php endif; ?>

                    <?php echo CHtml::form(['admin/authentication/sa/login'], 'post', ['id' => 'loginform', 'name' => 'loginform']); ?>

                    <div class="surveys-auth-fields">
                        <?php if ($multiAuth): ?>
                            <div class="mb-3">
                                <label for="authMethod" class="form-label"><?php eT('Authentication method'); ?></label>
                                <select name="authMethod" id="authMethod" class="form-select" onchange="this.form.submit();">
                                    <?php foreach ($possibleAuthMethods as $sKey => $sName): ?>
                                        <option value="<?php echo CHtml::encode($sKey); ?>"<?php echo $sKey === $selectedAuth ? ' selected' : ''; ?>><?php echo CHtml::encode($sName); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <?php echo CHtml::hiddenField('authMethod', $defaultAuth); ?>
                        <?php endif; ?>

                        <?php
                        // Username + password block, rendered by the selected auth plugin (Authdb):
                        // spans containing <label> + <input class="form-control">.
                        if (isset($pluginContent[$selectedAuth])) {
                            echo $pluginContent[$selectedAuth]->getContent();
                        }
                        ?>

                        <div class="mb-3">
                            <label for="loginlang" class="form-label"><?php eT('Language'); ?></label>
                            <select name="loginlang" id="loginlang" class="form-select">
                                <?php foreach ($languageData as $sLangKey => $sLangName): ?>
                                    <option value="<?php echo CHtml::encode($sLangKey); ?>"<?php echo (string) $sLangKey === (string) $language ? ' selected' : ''; ?>><?php echo CHtml::encode($sLangName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="action" value="login"/>
                    <input type="hidden" id="width" name="width" value=""/>
                    <button type="submit" class="btn btn-primary w-100" name="login_submit" value="login"><?php eT('Log in'); ?></button>

                    <?php echo CHtml::endForm(); ?>

                    <?php if ($forgotEnabled): ?>
                        <p class="text-center mt-3 mb-0">
                            <a href="<?php echo $this->createUrl('admin/authentication/sa/forgotpassword'); ?>"><?php eT('Forgot your password?'); ?></a>
                        </p>
                    <?php endif; ?>
                    <p class="text-center mt-3 mb-0">
                        <a href="<?php echo App()->createUrl('signup'); ?>"><?php eT("Don't have an account? Create your free account"); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Neutralize the admin-theme login chrome so this page matches the signup/landing shell. */
    body { background: #f8f9fa; }
    .surveys-auth { max-width: 1140px; margin-inline: auto; padding-block: 3rem; padding-inline: 1rem; }
    .surveys-auth .card { border: 0; border-radius: .75rem; }
    /* The Authdb plugin wraps each field in a bare <span>; make them stack + spaced like signup rows. */
    .surveys-auth-fields > span { display: block; margin-bottom: 1rem; }
    .surveys-auth-fields label { display: inline-block; margin-bottom: .5rem; font-weight: 500; }
    .surveys-auth-fields .form-control { width: 100%; }
</style>

<script type="text/javascript">
    $(document).ready(function () {
        $('#user').trigger('focus');
        $('#width').val($(window).width());
    });
    $(window).resize(function () {
        $('#width').val($(window).width());
    });
</script>
