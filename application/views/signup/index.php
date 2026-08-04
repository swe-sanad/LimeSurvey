<?php
/**
 * @var array<string,string> $errors field => message ('_general' for form-wide errors)
 * @var array<string,string> $values sticky field values (org_name, full_name, email)
 */
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-1"><?php eT('Create your organization'); ?></h1>
                <p class="text-muted mb-4"><?php eT('Get your own isolated workspace to build and manage surveys.'); ?></p>

                <?php if (!empty($errors['_general'])): ?>
                    <div class="alert alert-danger" role="alert"><?php echo CHtml::encode($errors['_general']); ?></div>
                <?php endif; ?>

                <?php echo CHtml::beginForm('', 'post', ['novalidate' => 'novalidate']); ?>

                <div class="mb-3">
                    <label for="org_name" class="form-label"><?php eT('Organization name'); ?></label>
                    <input
                        type="text"
                        class="form-control<?php echo !empty($errors['org_name']) ? ' is-invalid' : ''; ?>"
                        id="org_name"
                        name="org_name"
                        value="<?php echo CHtml::encode($values['org_name'] ?? ''); ?>"
                        required
                        <?php echo !empty($errors['org_name']) ? 'aria-describedby="org_name_error"' : ''; ?>
                    />
                    <?php if (!empty($errors['org_name'])): ?>
                        <div class="invalid-feedback" id="org_name_error"><?php echo CHtml::encode($errors['org_name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label"><?php eT('Full name'); ?></label>
                    <input
                        type="text"
                        class="form-control<?php echo !empty($errors['full_name']) ? ' is-invalid' : ''; ?>"
                        id="full_name"
                        name="full_name"
                        value="<?php echo CHtml::encode($values['full_name'] ?? ''); ?>"
                        required
                        <?php echo !empty($errors['full_name']) ? 'aria-describedby="full_name_error"' : ''; ?>
                    />
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="invalid-feedback" id="full_name_error"><?php echo CHtml::encode($errors['full_name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label"><?php eT('Email address'); ?></label>
                    <input
                        type="email"
                        class="form-control<?php echo !empty($errors['email']) ? ' is-invalid' : ''; ?>"
                        id="email"
                        name="email"
                        value="<?php echo CHtml::encode($values['email'] ?? ''); ?>"
                        required
                        <?php echo !empty($errors['email']) ? 'aria-describedby="email_error"' : ''; ?>
                    />
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback" id="email_error"><?php echo CHtml::encode($errors['email']); ?></div>
                    <?php endif; ?>
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
                    <?php
                    // LSCaptcha::renderImage() RETURNS the <img> (it does not echo like the parent
                    // CCaptcha), so run() alone only emits the refresh JS. Render the widget (to
                    // register that JS) then echo renderOut() to actually output the image.
                    $captcha = $this->widget('LSCaptcha', [
                        'captchaAction' => 'captcha',
                        'buttonType' => 'button',
                        'buttonOptions' => ['class' => 'btn btn-sm btn-outline-secondary'],
                        'buttonLabel' => gT('Reload image', 'unescaped'),
                        'imageOptions' => ['alt' => gT('Security question image'), 'class' => 'img-fluid mb-2 d-block'],
                    ]);
                    echo $captcha->renderOut();
                    ?>
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

                <button type="submit" class="btn btn-primary w-100"><?php eT('Create organization'); ?></button>
                <?php echo CHtml::endForm(); ?>

                <p class="text-center mt-3 mb-0">
                    <a href="<?php echo App()->createUrl('admin/authentication', ['sa' => 'login']); ?>"><?php eT('Already have an account? Log in'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>
