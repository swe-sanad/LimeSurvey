<?php
/**
 * Public set-password form for a team invite (Phase 1 multi-tenancy, Workflow C).
 * Mirrors application/views/signup/index.php's style exactly.
 *
 * @var string $token hidden field
 * @var array<string,string> $errors field => message ('_general' for form-wide errors), empty on first GET
 */
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-1"><?php eT('Set your password'); ?></h1>
                <p class="text-muted mb-4"><?php eT("You've been invited to join a team. Set a password to activate your account."); ?></p>

                <?php if (!empty($errors['_general'])): ?>
                    <div class="alert alert-danger" role="alert"><?php echo CHtml::encode($errors['_general']); ?></div>
                <?php endif; ?>

                <?php echo CHtml::beginForm('', 'post', ['novalidate' => 'novalidate']); ?>
                <input type="hidden" name="token" value="<?php echo CHtml::encode($token); ?>"/>

                <div class="mb-3">
                    <label for="full_name" class="form-label"><?php eT('Full name'); ?></label>
                    <input
                        type="text"
                        class="form-control"
                        id="full_name"
                        name="full_name"
                        autocomplete="name"
                    />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label"><?php eT('Password'); ?></label>
                    <input
                        type="password"
                        class="form-control<?php echo !empty($errors['password']) ? ' is-invalid' : ''; ?>"
                        id="password"
                        name="password"
                        required
                        <?php echo !empty($errors['password']) ? 'aria-describedby="password_error"' : ''; ?>
                    />
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback" id="password_error"><?php echo CHtml::encode($errors['password']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password_confirm" class="form-label"><?php eT('Confirm password'); ?></label>
                    <input
                        type="password"
                        class="form-control<?php echo !empty($errors['password_confirm']) ? ' is-invalid' : ''; ?>"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        <?php echo !empty($errors['password_confirm']) ? 'aria-describedby="password_confirm_error"' : ''; ?>
                    />
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <div class="invalid-feedback" id="password_confirm_error"><?php echo CHtml::encode($errors['password_confirm']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="loadsecurity" class="form-label"><?php eT('Security check'); ?></label>
                    <?php echo $this->widget('LSCaptcha', [
                        'captchaAction' => 'captcha',
                        'buttonType' => 'button',
                        'buttonOptions' => ['class' => 'btn btn-sm btn-outline-secondary'],
                        'buttonLabel' => gT('Reload image', 'unescaped'),
                        'imageOptions' => ['alt' => gT('Security question image'), 'class' => 'img-fluid mb-2 d-block'],
                    ], true); ?>
                    <input
                        type="text"
                        class="form-control<?php echo !empty($errors['captcha']) ? ' is-invalid' : ''; ?>"
                        id="loadsecurity"
                        name="loadsecurity"
                        required
                        <?php echo !empty($errors['captcha']) ? 'aria-describedby="captcha_error"' : ''; ?>
                        autocomplete="off"
                    />
                    <?php if (!empty($errors['captcha'])): ?>
                        <div class="invalid-feedback" id="captcha_error"><?php echo CHtml::encode($errors['captcha']); ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100"><?php eT('Activate account'); ?></button>
                <?php echo CHtml::endForm(); ?>

                <p class="text-center mt-3 mb-0">
                    <a href="<?php echo App()->createUrl('admin/authentication', ['sa' => 'login']); ?>"><?php eT('Already have an account? Log in'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>
