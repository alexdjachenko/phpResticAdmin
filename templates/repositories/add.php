<h2><?= htmlspecialchars(__('repo.add_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<form method="post" action="/repositories/add" class="add-repo-form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="repo-name"><?= htmlspecialchars(__('repo.name'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-name" name="name" required autofocus>
    </div>

    <div class="form-group">
        <label for="repo-type"><?= htmlspecialchars(__('repo.type'), ENT_QUOTES, 'UTF-8') ?></label>
        <select id="repo-type" name="type">
            <option value="local"><?= htmlspecialchars(__('repo.type_local'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="s3"><?= htmlspecialchars(__('repo.type_s3'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="sftp"><?= htmlspecialchars(__('repo.type_sftp'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="rest"><?= htmlspecialchars(__('repo.type_rest'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
    </div>

    <div class="form-group">
        <label for="repo-path"><?= htmlspecialchars(__('repo.path'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-path" name="path" required placeholder="/backups/my-repo">
        <span class="form-help"><?= htmlspecialchars(__('repo.path_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-group">
        <label for="repo-password"><?= htmlspecialchars(__('repo.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="repo-password" name="password">
        <span class="form-help"><?= htmlspecialchars(__('repo.password_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div id="s3-fields" style="display:none">
        <div class="form-group">
            <label for="s3-key"><?= htmlspecialchars(__('repo.s3_key'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" id="s3-key" name="s3_key">
        </div>
        <div class="form-group">
            <label for="s3-secret"><?= htmlspecialchars(__('repo.s3_secret'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" id="s3-secret" name="s3_secret">
        </div>
        <div class="form-group">
            <label for="s3-endpoint"><?= htmlspecialchars(__('repo.s3_endpoint'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" id="s3-endpoint" name="s3_endpoint" placeholder="https://s3.amazonaws.com">
            <span class="form-help"><?= htmlspecialchars(__('repo.s3_endpoint_help'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="form-group">
        <label><?= htmlspecialchars(__('repo.category_label'), ENT_QUOTES, 'UTF-8') ?></label>
        <div class="category-options">
            <?php $first = true; foreach ($categories as $catKey => $catLabel): ?>
                <label class="category-option">
                    <input type="radio" name="category" value="<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>" <?= $first ? 'checked' : '' ?>>
                    <span class="category-badge badge-<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            <?php $first = false; endforeach ?>
        </div>
    </div>

    <div class="form-group">
        <label for="backup-paths"><?= htmlspecialchars(__('repo.backup_paths'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea id="backup-paths" name="backup_paths" rows="4" placeholder="/home&#10;/etc"></textarea>
        <span class="form-help"><?= htmlspecialchars(__('repo.backup_paths_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <?php if (!empty($canInit)): ?>
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="init_repo" value="1">
            <?= htmlspecialchars(__('repo.init_checkbox'), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <span class="form-help"><?= htmlspecialchars(__('repo.init_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif ?>

    <div class="form-actions">
        <button type="submit"><?= htmlspecialchars(__('form.submit'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="/repositories" class="btn-cancel"><?= htmlspecialchars(__('form.cancel'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</form>

<script>
(function() {
    var typeSelect = document.getElementById('repo-type');
    var s3Fields = document.getElementById('s3-fields');

    function toggleS3Fields() {
        s3Fields.style.display = typeSelect.value === 's3' ? 'block' : 'none';
    }

    typeSelect.addEventListener('change', toggleS3Fields);
    toggleS3Fields();
})();
</script>
