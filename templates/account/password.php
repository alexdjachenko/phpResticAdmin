<h2><?= htmlspecialchars(__('account.password_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<form method="post" action="/account/password">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="current_password"><?= htmlspecialchars(__('account.current_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="current_password" name="current_password" required>
    </div>

    <div class="form-group">
        <label for="new_password"><?= htmlspecialchars(__('account.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="new_password" name="new_password" required>
    </div>

    <div class="form-group">
        <label for="confirm_password"><?= htmlspecialchars(__('account.confirm_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>

    <button type="submit" class="btn-primary"><?= htmlspecialchars(__('form.submit'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
