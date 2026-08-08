<h2><?= htmlspecialchars(__('auth.login_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<form method="post" action="/login" class="login-form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="username"><?= htmlspecialchars(__('auth.username'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="username" name="username" required autofocus>
    </div>

    <div class="form-group">
        <label for="password"><?= htmlspecialchars(__('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit"><?= htmlspecialchars(__('auth.sign_in'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
