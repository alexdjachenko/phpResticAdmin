<div class="breadcrumb">
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2><?= htmlspecialchars(__('keys.list'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($keys)): ?>
<table class="key-table">
    <thead>
        <tr>
            <th><?= htmlspecialchars(__('keys.id'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.user'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.created'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.current'), ENT_QUOTES, 'UTF-8') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($keys as $key): ?>
        <?php $keyId = $key['id'] ?? ''; $shortId = substr($keyId, 0, 8); ?>
        <tr>
            <td><code><?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?></code></td>
            <td><?= htmlspecialchars($key['userName'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($key['created'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?php if (!empty($key['current'])): ?><span class="key-current">&#10003;</span><?php endif ?></td>
            <td>
                <?php if (empty($key['current'])): ?>
                <form method="post" action="/keys/remove" style="display:inline" onsubmit="return confirm('<?= htmlspecialchars(__('keys.confirm_remove'), ENT_QUOTES, 'UTF-8') ?>')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="key_id" value="<?= htmlspecialchars($keyId, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn-danger-sm"><?= htmlspecialchars(__('keys.remove'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
                <?php endif ?>
                <form method="post" action="/keys/passwd" style="display:inline">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="key_id" value="<?= htmlspecialchars($keyId, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="password" name="new_password" placeholder="<?= htmlspecialchars(__('keys.new_password'), ENT_QUOTES, 'UTF-8') ?>" required style="width:120px">
                    <button type="submit" class="btn-primary-sm"><?= htmlspecialchars(__('keys.change_pass'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
    <p>No keys found.</p>
<?php endif ?>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.verify'), ENT_QUOTES, 'UTF-8') ?></h3>
    <form method="post" action="/keys/verify">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label><?= htmlspecialchars(__('keys.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('keys.verify_button'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.add_key'), ENT_QUOTES, 'UTF-8') ?></h3>
    <form method="post" action="/keys/add">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label><?= htmlspecialchars(__('keys.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" name="new_password" required>
        </div>
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('keys.add_key'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>
