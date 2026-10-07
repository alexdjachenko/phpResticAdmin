<?php
$isEdit = ($mode ?? 'add') === 'edit';
$formAction = $isEdit ? '/users/edit' : '/users/add';
$editingName = $editingUser['username'] ?? '';
$data = $editingUser['data'] ?? [];
$repos = $data['repos'] ?? [];
?>

<h2><?= htmlspecialchars($isEdit ? __('users.edit_title') : __('users.add_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="username"><?= htmlspecialchars(__('auth.username'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($editingName, ENT_QUOTES, 'UTF-8') ?>"
               <?= $isEdit ? 'disabled' : 'required' ?>>
        <?php if ($isEdit): ?>
            <input type="hidden" name="username" value="<?= htmlspecialchars($editingName, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif ?>
    </div>

    <div class="form-group">
        <label for="password"><?= htmlspecialchars(__('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="password" name="password">
        <?php if ($isEdit): ?>
            <p class="hint"><?= htmlspecialchars(__('users.password_edit_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif ?>
    </div>

    <div class="form-group">
        <label for="password_var"><?= htmlspecialchars(__('users.password_var'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="password_var" name="password_var"
               value="<?= htmlspecialchars($data['password_var'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <p class="hint"><?= htmlspecialchars(__('users.password_var_help'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="form-group">
        <label for="api_tokens"><?= htmlspecialchars(__('users.api_tokens'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea id="api_tokens" name="api_tokens" rows="3"><?= htmlspecialchars(implode("\n", $data['api_tokens'] ?? []), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <fieldset>
        <legend><?= htmlspecialchars(__('users.permissions'), ENT_QUOTES, 'UTF-8') ?></legend>
        <label><input type="checkbox" name="can_init" value="1" <?= !empty($data['can_init']) ? 'checked' : '' ?>> <?= htmlspecialchars(__('users.can_init'), ENT_QUOTES, 'UTF-8') ?></label><br>
        <label><input type="checkbox" name="can_delete" value="1" <?= !empty($data['can_delete']) ? 'checked' : '' ?>> <?= htmlspecialchars(__('users.can_delete'), ENT_QUOTES, 'UTF-8') ?></label><br>
        <label><input type="checkbox" name="can_manage_users" value="1" <?= !empty($data['can_manage_users']) ? 'checked' : '' ?>> <?= htmlspecialchars(__('users.manage_users'), ENT_QUOTES, 'UTF-8') ?></label><br>
        <label><input type="checkbox" name="can_manage_processes" value="1" <?= !empty($data['can_manage_processes']) ? 'checked' : '' ?>> <?= htmlspecialchars(__('users.manage_processes'), ENT_QUOTES, 'UTF-8') ?></label>
    </fieldset>

    <h3><?= htmlspecialchars(__('users.repos_matrix'), ENT_QUOTES, 'UTF-8') ?></h3>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('repo.category'), ENT_QUOTES, 'UTF-8') ?></th>
                <th>use</th>
                <th>use_read</th>
                <th>use_write</th>
                <th>edit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (['public', 'private', 'session'] as $cat): ?>
                <?php
                $catData = $repos[$cat] ?? [];
                ?>
                <tr>
                    <td><?= htmlspecialchars(__('repo.category.' . $cat), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><input type="checkbox" name="repos[<?= $cat ?>][use]" value="1" <?= !empty($catData['use']) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="repos[<?= $cat ?>][use_read]" value="1" <?= !empty($catData['use_read']) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="repos[<?= $cat ?>][use_write]" value="1" <?= !empty($catData['use_write']) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="repos[<?= $cat ?>][edit]" value="1" <?= !empty($catData['edit']) ? 'checked' : '' ?>></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <div class="form-group">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('form.submit'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="/users"><?= htmlspecialchars(__('form.cancel'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</form>
