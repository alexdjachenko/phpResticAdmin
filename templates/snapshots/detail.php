<?php
$summary = $snap['summary'] ?? [];
$statsComputedAt = $statsEntry['computed_at'] ?? null;
$stats = $statsEntry['stats'] ?? null;
$pollInterval = (int) (\App\Core\App::configStorage()->loadSettings()['task_poll_interval'] ?? 3000);
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

<h3><?= htmlspecialchars(__('snap.stats_title'), ENT_QUOTES, 'UTF-8') ?></h3>
<div id="stats-block">
    <div id="stats-data">
        <?php if ($stats !== null): ?>
            <table class="repo-info">
                <tr><th><?= htmlspecialchars(__('snap.stats_total_size'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars(\App\Helpers\Format::bytes((int) ($stats['total_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th><?= htmlspecialchars(__('snap.stats_file_count'), ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($stats['total_file_count'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
            </table>
            <p class="muted"><?= htmlspecialchars(__('snap.data_at', ['{date}' => \App\Helpers\Format::date(date('c', (int) $statsComputedAt))]), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif ?>
    </div>
    <div id="stats-progress" class="snap-progress" hidden></div>
    <button type="button" id="btn-stats"
            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-snap-id="<?= htmlspecialchars($snap['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($stats !== null ? __('snap.stats_recalc') : __('snap.stats_load'), ENT_QUOTES, 'UTF-8') ?>
    </button>
</div>

<div class="repo-actions">
    <a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-snapshots"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/export?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-export"><?= htmlspecialchars(__('export.export_snap'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php if (!empty($destRepos)): ?>
    <button id="btn-copy-snap"
            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-snap-id="<?= htmlspecialchars($snap['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars(__('snap.copy_button'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <?php endif ?>
</div>

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
    var dataDiv = document.getElementById('stats-data');
    var progress = document.getElementById('stats-progress');
    if (!btn || !dataDiv) return;

    var poll = <?= (int) $pollInterval ?>;
    var snapId = btn.dataset.snapId;
    var csrf = function () { return btn.dataset.csrf; };
    var states = {
        queued: <?= json_encode(__('tasks.state_queued')) ?>,
        running: <?= json_encode(__('tasks.state_running')) ?>
    };
    var bytes = function (b) {
        var units = ['B','KiB','MiB','GiB','TiB'], i = 0;
        b = Number(b) || 0;
        while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
        return (i === 0 ? b : b.toFixed(2)) + ' ' + units[i];
    };
    function render(stats, computedAt) {
        var d = new Date(computedAt * 1000);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var when = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        dataDiv.innerHTML =
            '<table class="repo-info">' +
            '<tr><th>' + <?= json_encode(__('snap.stats_total_size')) ?> + '</th><td>' + bytes(stats.total_size || 0) + '</td></tr>' +
            '<tr><th>' + <?= json_encode(__('snap.stats_file_count')) ?> + '</th><td>' + (stats.total_file_count != null ? stats.total_file_count : '—') + '</td></tr>' +
            '</table>' +
            '<p class="muted">' + <?= json_encode(__('snap.data_at')) ?>.replace('{date}', when) + '</p>';
    }
    function finish(label) {
        fetch('/snapshots/stats/result?snap_id=' + encodeURIComponent(snapId) + '&label=' + encodeURIComponent(label))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                progress.hidden = true;
                btn.disabled = false;
                btn.textContent = <?= json_encode(__('snap.stats_recalc')) ?>;
                if (data && data.ok && data.stats) {
                    render(data.stats, data.computed_at || Math.floor(Date.now() / 1000));
                } else {
                    dataDiv.insertAdjacentHTML('beforeend', '<p class="flash flash-error">' + ((data && data.error) || '') + '</p>');
                }
            })
            .catch(function () { progress.hidden = true; btn.disabled = false; });
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        var formData = new URLSearchParams();
        formData.append('repo_id', btn.dataset.repoId);
        formData.append('snap_id', snapId);
        formData.append('_csrf_token', csrf());
        fetch('/snapshots/stats', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data._csrf_token) { btn.dataset.csrf = data._csrf_token; }
            if (!data.ok || !data.label) {
                btn.disabled = false;
                dataDiv.insertAdjacentHTML('beforeend', '<p class="flash flash-error">' + (data.error || '') + '</p>');
                return;
            }
            var label = data.label;
            progress.hidden = false;
            progress.textContent = states.queued;
            if (window.TaskUI && window.TaskUI.open) { window.TaskUI.open(label, data.title || label); }
            var timer = setInterval(function () {
                if (document.visibilityState !== 'visible') { return; }
                fetch('/tasks/status?label=' + encodeURIComponent(label), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (st) {
                        if (!st || !st.ok) { return; }
                        if (st.state !== 'finished') {
                            progress.textContent = (states[st.state] || st.state) + (st.position ? ' (' + st.position + ')' : '');
                            return;
                        }
                        clearInterval(timer);
                        finish(label);
                    })
                    .catch(function () {});
            }, poll);
        })
        .catch(function () { btn.disabled = false; });
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

            if (data.ok && data.label) {
                copyModal.style.display = 'none';
                if (window.TaskUI && window.TaskUI.open) {
                    window.TaskUI.open(data.label, data.title || data.label);
                } else if (data.stream_url) {
                    window.location.href = data.stream_url;
                }
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
