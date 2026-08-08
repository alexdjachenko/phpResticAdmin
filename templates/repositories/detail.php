<?php
$cat = $category;
$catLabel = __('repo.category.' . $cat);
$badgeClass = 'badge-' . $cat;
$backupPaths = $repo['backup_paths'] ?? [];
?>
<p><a href="/repositories" class="back-link"><?= htmlspecialchars(__('repo.detail_back'), ENT_QUOTES, 'UTF-8') ?></a></p>

<div class="repo-detail">
    <h2>
        <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <span class="category-badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
    </h2>

    <table class="repo-info">
        <tr>
            <th><?= htmlspecialchars(__('repo.type'), ENT_QUOTES, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($repo['type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <th><?= htmlspecialchars(__('repo.path'), ENT_QUOTES, 'UTF-8') ?></th>
            <td><code><?= htmlspecialchars($repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
        </tr>
        <?php if (!empty($backupPaths)): ?>
        <tr>
            <th><?= htmlspecialchars(__('repo.backup_paths'), ENT_QUOTES, 'UTF-8') ?></th>
            <td>
                <ul class="path-list">
                    <?php foreach ($backupPaths as $bp): ?>
                        <li><code><?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?></code></li>
                    <?php endforeach ?>
                </ul>
            </td>
        </tr>
        <?php endif ?>
        <?php if (!empty($repo['env']['AWS_ACCESS_KEY_ID'])): ?>
        <tr>
            <th>S3 Key</th>
            <td><code><?= htmlspecialchars(\App\Helpers\Format::truncate($repo['env']['AWS_ACCESS_KEY_ID'], 20), ENT_QUOTES, 'UTF-8') ?></code></td>
        </tr>
        <?php endif ?>
    </table>

    <div class="repo-actions">
        <?php if ($isLoggedIn): ?>
            <button class="btn-check"
                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(__('repo.check'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        <?php endif ?>

        <?php if ($canBackup): ?>
            <form method="post" action="/repositories/backup?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-backup"><?= htmlspecialchars(__('repo.backup'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        <?php else: ?>
            <span class="no-backup-hint"><?= htmlspecialchars(__('repo.no_backup_paths'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif ?>

        <a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-snapshots"><?= htmlspecialchars(__('repo.snapshots'), ENT_QUOTES, 'UTF-8') ?></a>

        <?php if ($canEdit): ?>
            <a href="/repositories/edit?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-edit"><?= htmlspecialchars(__('repo.edit'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif ?>

        <?php if ($canMove && !empty($availableCategories)): ?>
            <select class="move-dropdown"
                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= htmlspecialchars(__('repo.move_to'), ENT_QUOTES, 'UTF-8') ?>...</option>
                <?php foreach ($availableCategories as $catKey => $catLabel): ?>
                    <option value="<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach ?>
            </select>
        <?php endif ?>

        <?php if ($canDelete): ?>
            <button class="btn-delete"
                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(__('repo.delete'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        <?php endif ?>
    </div>

    <div class="repo-status" id="repo-status" data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
</div>

<?php if (!empty($latestSnapshots)): ?>
<div class="repo-snapshots">
    <h3><?= htmlspecialchars(__('repo.latest_snaps'), ENT_QUOTES, 'UTF-8') ?></h3>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('snap.id'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($latestSnapshots as $snap): ?>
                <tr>
                    <td><code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(implode(', ', array_map(function(string $p): string { return basename($p); }, $snap['paths'] ?? [])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::bytes((int) ($snap['summary']['total_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php if ($totalSnapshots > 5): ?>
        <p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('repo.view_all_snaps'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php endif ?>
</div>
<?php endif ?>

<script>
function sendPost(url, body, csrfEl) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(resp) { return resp.json(); })
    .then(function(data) {
        if (data._csrf_token && csrfEl) csrfEl.dataset.csrf = data._csrf_token;
        if (data._csrf_token) {
            document.querySelectorAll('[data-csrf]').forEach(function(el) {
                el.dataset.csrf = data._csrf_token;
            });
        }
        return data;
    });
}

// Check button
document.querySelectorAll('.btn-check').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var repoId = this.dataset.repoId;
        var statusEl = document.getElementById('repo-status');

        statusEl.textContent = <?= json_encode(__('repo.status_checking')) ?>;
        statusEl.className = 'repo-status checking';

        var formData = new URLSearchParams();
        formData.append('repo_id', repoId);
        formData.append('_csrf_token', this.dataset.csrf);

        sendPost('/repositories/check', formData.toString(), this)
        .then(function(data) {
            if (data.ok) {
                statusEl.textContent = <?= json_encode(__('repo.status_ok')) ?>;
                statusEl.className = 'repo-status ok';
            } else {
                statusEl.textContent = data.error || <?= json_encode(__('repo.status_error')) ?>;
                statusEl.className = 'repo-status error';
            }
        })
        .catch(function(err) {
            statusEl.textContent = <?= json_encode(__('repo.status_failed')) ?>;
            statusEl.className = 'repo-status error';
        });
    });
});

// Move dropdown
document.querySelectorAll('.move-dropdown').forEach(function(select) {
    select.addEventListener('change', function() {
        var toCategory = this.value;
        if (!toCategory) return;
        var repoId = this.dataset.repoId;

        var formData = new URLSearchParams();
        formData.append('repo_id', repoId);
        formData.append('to_category', toCategory);
        formData.append('_csrf_token', this.dataset.csrf);

        sendPost('/repositories/move', formData.toString(), this)
        .then(function(data) {
            if (data.ok) { window.location.reload(); }
            else { alert(data.error || 'Error'); }
        })
        .catch(function() { alert('Network error'); });
    });
});

// Delete button
document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm(<?= json_encode(__('repo.confirm_delete')) ?>)) return;
        var repoId = this.dataset.repoId;

        var formData = new URLSearchParams();
        formData.append('repo_id', repoId);
        formData.append('_csrf_token', this.dataset.csrf);

        sendPost('/repositories/delete', formData.toString(), this)
        .then(function(data) {
            if (data.ok) { window.location.href = '/repositories'; }
            else { alert(data.error || 'Error'); }
        })
        .catch(function() { alert('Network error'); });
    });
});
</script>
