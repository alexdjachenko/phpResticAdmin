<?php
$summary = $snap['summary'] ?? [];
?>
<p>
    <a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?></a>
    &gt;
    <code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
</p>

<h2><?= htmlspecialchars(__('snap.detail_title'), ENT_QUOTES, 'UTF-8') ?></h2>

<table class="repo-info">
    <tr><th>ID</th><td><code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.host'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars($snap['hostname'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars(implode(', ', $snap['paths'] ?? []), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.tags'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars(implode(', ', $snap['tags'] ?? []), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php if (!empty($snap['parent'])): ?>
    <tr><th>Parent</th><td><code><?= htmlspecialchars(substr($snap['parent'], 0, 8), ENT_QUOTES, 'UTF-8') ?></code></td></tr>
    <?php endif ?>
</table>

<h3><?= htmlspecialchars(__('snap.summary_title'), ENT_QUOTES, 'UTF-8') ?></h3>
<table class="repo-info">
    <tr><th><?= htmlspecialchars(__('snap.summary_processed'), ENT_QUOTES, 'UTF-8') ?></th><td><?= isset($summary['total_bytes_processed']) ? htmlspecialchars(\App\Helpers\Format::bytes((int) $summary['total_bytes_processed']), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.summary_added'), ENT_QUOTES, 'UTF-8') ?></th><td><?= isset($summary['data_added']) ? htmlspecialchars(\App\Helpers\Format::bytes((int) $summary['data_added']), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.summary_files'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($summary['total_files_processed'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.summary_files_new'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($summary['files_new'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.summary_files_changed'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($summary['files_changed'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th><?= htmlspecialchars(__('snap.summary_dirs'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($summary['dirs_new'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>

<div class="repo-actions">
    <a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-snapshots"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/export?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-export"><?= htmlspecialchars(__('export.export_snap'), ENT_QUOTES, 'UTF-8') ?></a>
    <button id="btn-stats"
            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-snap-id="<?= htmlspecialchars($snap['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars(__('snap.stats_load'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <?php if (!empty($destRepos)): ?>
    <button id="btn-copy-snap"
            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-snap-id="<?= htmlspecialchars($snap['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars(__('snap.copy_button'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <?php endif ?>
</div>

<div id="stats-result" class="stats-result"></div>

<?php if (!empty($destRepos)): ?>
<div id="copy-modal" class="modal" style="display:none">
    <div class="modal-content">
        <h3><?= htmlspecialchars(__('snap.copy_title'), ENT_QUOTES, 'UTF-8') ?></h3>
        <p>
            <label for="copy-dest-repo"><?= htmlspecialchars(__('snap.copy_select_dest'), ENT_QUOTES, 'UTF-8') ?></label>
            <select id="copy-dest-repo"></select>
        </p>
        <div class="modal-actions">
            <button id="btn-copy-confirm"><?= htmlspecialchars(__('form.submit'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btn-copy-cancel"><?= htmlspecialchars(__('form.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="copy-result"></div>
    </div>
</div>
<?php endif ?>

<script>
(function() {
    var btn = document.getElementById('btn-stats');
    var resultDiv = document.getElementById('stats-result');
    if (!btn || !resultDiv) return;

    btn.addEventListener('click', function() {
        var repoId = btn.dataset.repoId;
        var snapId = btn.dataset.snapId;
        var csrf = btn.dataset.csrf;

        btn.textContent = <?= json_encode(__('snap.stats_loading')) ?>;
        btn.disabled = true;
        resultDiv.innerHTML = '';

        var formData = new URLSearchParams();
        formData.append('repo_id', repoId);
        formData.append('snap_id', snapId);
        formData.append('_csrf_token', csrf);

        fetch('/snapshots/stats', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data._csrf_token) btn.dataset.csrf = data._csrf_token;

            if (data.ok && data.stream_url) {
                window.location.href = data.stream_url;
                return;
            }

            btn.textContent = <?= json_encode(__('snap.stats_load')) ?>;
            btn.disabled = false;
            resultDiv.innerHTML = '<p class="flash flash-error">' + (data.error || 'Error') + '</p>';
        })
        .catch(function() {
            btn.textContent = <?= json_encode(__('snap.stats_load')) ?>;
            btn.disabled = false;
            resultDiv.innerHTML = '<p class="flash flash-error">Network error</p>';
        });
        });
        })();

    <?php if (!empty($destRepos)): ?>
    (function() {
    var copyBtn = document.getElementById('btn-copy-snap');
    var copyModal = document.getElementById('copy-modal');
    var copyDestSelect = document.getElementById('copy-dest-repo');
    var copyConfirm = document.getElementById('btn-copy-confirm');
    var copyCancel = document.getElementById('btn-copy-cancel');
    var copyResult = document.getElementById('copy-result');

    if (!copyBtn || !copyModal) return;

    var destRepos = <?= json_encode($destRepos) ?>;

    copyBtn.addEventListener('click', function() {
        copyDestSelect.innerHTML = '';
        destRepos.forEach(function(r) {
            var opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name;
            copyDestSelect.appendChild(opt);
        });
        copyResult.innerHTML = '';
        copyModal.style.display = 'block';
    });

    copyCancel.addEventListener('click', function() {
        copyModal.style.display = 'none';
    });

    copyConfirm.addEventListener('click', function() {
        var destRepoId = copyDestSelect.value;
        if (!destRepoId) return;

        copyConfirm.disabled = true;
        copyResult.innerHTML = '';

        var formData = new URLSearchParams();
        formData.append('source_repo_id', copyBtn.dataset.repoId);
        formData.append('dest_repo_id', destRepoId);
        formData.append('snap_id', copyBtn.dataset.snapId);
        formData.append('_csrf_token', copyBtn.dataset.csrf);

        fetch('/snapshots/copy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data._csrf_token) {
                copyBtn.dataset.csrf = data._csrf_token;
            }
            copyConfirm.disabled = false;

            if (data.ok && data.stream_url) {
                window.location.href = data.stream_url;
                return;
            }

            copyResult.innerHTML = '<p class="flash flash-error">' + <?= json_encode(__('snap.copy_failed')) ?> + ' ' + (data.error || '') + '</p>';
            })
        .catch(function() {
            copyConfirm.disabled = false;
            copyResult.innerHTML = '<p class="flash flash-error">Network error</p>';
        });
    });
    })();
    <?php endif ?>
    </script>
