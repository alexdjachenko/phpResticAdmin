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
</div>

<div id="stats-result" class="stats-result"></div>

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
            btn.textContent = <?= json_encode(__('snap.stats_load')) ?>;
            btn.disabled = false;

            if (data.ok && data.stats) {
                var s = data.stats;
                var html = '<h3><?= htmlspecialchars(__('snap.stats_title'), ENT_QUOTES, 'UTF-8') ?></h3>';
                html += '<table class="repo-info">';
                if (s.total_size !== undefined) html += '<tr><th>Total Size</th><td>' + formatBytes(s.total_size) + '</td></tr>';
                if (s.total_file_count !== undefined) html += '<tr><th>Files</th><td>' + s.total_file_count + '</td></tr>';
                if (s.total_blob_count !== undefined) html += '<tr><th>Blobs</th><td>' + s.total_blob_count + '</td></tr>';
                html += '</table>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<p class="flash flash-error">' + (data.error || 'Error') + '</p>';
            }
        })
        .catch(function() {
            btn.textContent = <?= json_encode(__('snap.stats_load')) ?>;
            btn.disabled = false;
            resultDiv.innerHTML = '<p class="flash flash-error">Network error</p>';
        });
    });

    function formatBytes(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(2) + ' KiB';
        if (b < 1073741824) return (b / 1048576).toFixed(2) + ' MiB';
        return (b / 1073741824).toFixed(2) + ' GiB';
    }
})();
</script>
