<?php
$backupPathsStr = '';
if (!empty($repo['backup_paths'])) {
    $backupPathsStr = implode("\n", $repo['backup_paths']);
}
$repoType = $repo['type'] ?? 'local';
?>
<p><a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="back-link"><?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a></p>

<h2><?= htmlspecialchars(__('repo.edit_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<form method="post" action="/repositories/edit" class="add-repo-form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="repo-name"><?= htmlspecialchars(__('repo.name'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-name" name="name" required value="<?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autofocus>
    </div>

    <div class="form-group">
        <label for="repo-type"><?= htmlspecialchars(__('repo.type'), ENT_QUOTES, 'UTF-8') ?></label>
        <select id="repo-type" name="type">
            <?php
            $types = ['local' => __('repo.type_local'), 's3' => __('repo.type_s3'), 'sftp' => __('repo.type_sftp'), 'rest' => __('repo.type_rest')];
            foreach ($types as $tKey => $tLabel):
                $sel = ($repo['type'] ?? '') === $tKey ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($tKey, ENT_QUOTES, 'UTF-8') ?>" <?= $sel ?>><?= htmlspecialchars($tLabel, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="form-group" data-location-field="local" style="display:<?= $repoType === 'local' ? 'block' : 'none' ?>">
        <label for="repo-path"><?= htmlspecialchars(__('repo.path'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-path" name="local_path" value="<?= htmlspecialchars($repo['local_path'] ?? $repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <span class="form-help"><?= htmlspecialchars(__('repo.path_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-group" data-location-field="s3" style="display:<?= $repoType === 's3' ? 'block' : 'none' ?>">
        <label for="repo-bucket"><?= htmlspecialchars(__('repo.s3_bucket'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-bucket" name="s3_bucket" value="<?= htmlspecialchars($repo['s3_bucket'] ?? $repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <span class="form-help"><?= htmlspecialchars(__('repo.s3_bucket_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-group" data-location-field="sftp" style="display:<?= $repoType === 'sftp' ? 'block' : 'none' ?>">
        <label for="repo-sftp-path"><?= htmlspecialchars(__('repo.sftp_path'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-sftp-path" name="sftp_path" value="<?= htmlspecialchars($repo['sftp_path'] ?? $repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <span class="form-help"><?= htmlspecialchars(__('repo.sftp_path_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-group" data-location-field="rest" style="display:<?= $repoType === 'rest' ? 'block' : 'none' ?>">
        <label for="repo-rest-url"><?= htmlspecialchars(__('repo.rest_url'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" id="repo-rest-url" name="rest_url" value="<?= htmlspecialchars($repo['rest_url'] ?? $repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <span class="form-help"><?= htmlspecialchars(__('repo.rest_url_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-group">
        <label for="repo-password"><?= htmlspecialchars(__('repo.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="repo-password" name="password" placeholder="••••••••">
        <span class="form-help"><?= htmlspecialchars(__('repo.password_placeholder'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div id="s3-fields" style="display:<?= ($repo['type'] ?? '') === 's3' ? 'block' : 'none' ?>">
        <div class="form-group">
            <label for="s3-key"><?= htmlspecialchars(__('repo.s3_key'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" id="s3-key" name="s3_key" value="<?= htmlspecialchars($repo['env']['AWS_ACCESS_KEY_ID'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="••••••••">
        </div>
        <div class="form-group">
            <label for="s3-secret"><?= htmlspecialchars(__('repo.s3_secret'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" id="s3-secret" name="s3_secret" placeholder="••••••••">
            <span class="form-help"><?= htmlspecialchars(__('repo.s3_secret_placeholder'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <div class="form-group">
            <label for="s3-endpoint"><?= htmlspecialchars(__('repo.s3_endpoint'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" id="s3-endpoint" name="s3_endpoint" value="<?= htmlspecialchars($repo['env']['AWS_ENDPOINT'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://s3.amazonaws.com">
            <span class="form-help"><?= htmlspecialchars(__('repo.s3_endpoint_help'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="form-group">
        <label for="backup-paths"><?= htmlspecialchars(__('repo.backup_paths'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea id="backup-paths" name="backup_paths" rows="4" placeholder="/home&#10;/etc"><?= htmlspecialchars($backupPathsStr, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span class="form-help"><?= htmlspecialchars(__('repo.backup_paths_help'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="form-actions">
        <button type="submit"><?= htmlspecialchars(__('form.submit'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel"><?= htmlspecialchars(__('form.cancel'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</form>

<script>
(function() {
    var typeSelect = document.getElementById('repo-type');
    var s3Fields = document.getElementById('s3-fields');

    function toggleTypeFields() {
        var type = typeSelect.value;
        s3Fields.style.display = type === 's3' ? 'block' : 'none';
        document.querySelectorAll('[data-location-field]').forEach(function(el) {
            el.style.display = el.getAttribute('data-location-field') === type ? 'block' : 'none';
        });
    }

    typeSelect.addEventListener('change', toggleTypeFields);
})();
</script>
