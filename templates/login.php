<h2>Login</h2>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<form method="post" action="/login" class="login-form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Sign in</button>
</form>
