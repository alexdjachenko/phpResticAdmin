<?php
$state = $view['state'] ?? 'list';
$pollInterval = (int) (\App\Core\App::configStorage()->loadSettings()['task_poll_interval'] ?? 3000);
?>
<?php if ($repo !== null): ?>
<div class="breadcrumb">
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2>
    <?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?>
    <span class="snap-repo-name">— <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
</h2>
<p><code><?= htmlspecialchars($repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></p>

<form method="post" action="/snapshots/refresh" style="display:inline">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn-snapshots"><?= htmlspecialchars(__('snap.refresh'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php else: ?>
<h2><?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if ($repo !== null && $state === 'progress'): ?>
    <?php
    $label = $view['task_label'] ?? '';
    $taskState = $view['task_state'] ?? 'queued';
    $position = $view['position'] ?? null;
    ?>
    <p class="snap-progress" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
        <span class="snap-progress-state"><?= htmlspecialchars(__('tasks.state_' . $taskState), ENT_QUOTES, 'UTF-8') ?><?= $position !== null ? ' (' . htmlspecialchars(__('tasks.queued_position', ['{n}' => $position]), ENT_QUOTES, 'UTF-8') . ')' : '' ?></span>
    </p>
    <div class="repo-actions">
        <button type="button" id="snap-refresh-status"><?= htmlspecialchars(__('snap.refresh_status'), ENT_QUOTES, 'UTF-8') ?></button>
        <?php if ($taskState === 'queued'): ?>
            <button type="button" id="snap-promote"><?= htmlspecialchars(__('tasks.promote'), ENT_QUOTES, 'UTF-8') ?></button>
        <?php endif ?>
        <button type="button" id="snap-cancel"><?= htmlspecialchars(__('tasks.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="/tasks/view?label=<?= htmlspecialchars(urlencode($label), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('tasks.full_output'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <script>
    (function () {
        var el = document.querySelector('.snap-progress');
        if (!el) { return; }
        var label = el.dataset.label;
        var stateEl = el.querySelector('.snap-progress-state');
        var poll = <?= (int) $pollInterval ?>;
        var states = {
            queued: <?= json_encode(__('tasks.state_queued')) ?>,
            running: <?= json_encode(__('tasks.state_running')) ?>,
            finished: <?= json_encode(__('tasks.state_finished')) ?>
        };
        function csrf() {
            var b = document.body;
            return b ? b.dataset.csrf : '';
        }
        function post(url) {
            var body = new URLSearchParams();
            body.append('label', label);
            body.append('_csrf_token', csrf());
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body.toString() })
                .then(function (r) { return r.json(); });
        }
        var btnRefresh = document.getElementById('snap-refresh-status');
        if (btnRefresh) { btnRefresh.addEventListener('click', function () { window.location.reload(); }); }
        var btnPromote = document.getElementById('snap-promote');
        if (btnPromote) { btnPromote.addEventListener('click', function () { post('/tasks/promote'); }); }
        var btnCancel = document.getElementById('snap-cancel');
        if (btnCancel) { btnCancel.addEventListener('click', function () { if (window.confirm(<?= json_encode(__('tasks.confirm_cancel')) ?>)) { post('/tasks/cancel'); } }); }

        function tick() {
            if (document.visibilityState !== 'visible') { return; }
            fetch('/tasks/status?label=' + encodeURIComponent(label), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) { return; }
                    if (data.state === 'finished') {
                        window.location.reload();
                        return;
                    }
                    if (stateEl) {
                        stateEl.textContent = (states[data.state] || data.state) + (data.position ? ' (' + data.position + ')' : '');
                    }
                })
                .catch(function () {});
        }
        setInterval(tick, poll);
    })();
    </script>
<?php elseif ($repo !== null && $state === 'error'): ?>
    <p class="flash flash-error"><?= htmlspecialchars($view['error'] ?? __('snap.load_error'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/snapshots/refresh">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-snapshots"><?= htmlspecialchars(__('snap.retry'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
<?php elseif ($repo !== null): ?>
    <?php if (empty($view['snapshots'])): ?>
        <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <?php
        $snapshots = $view['snapshots'];
        $duration = $view['duration'] ?? null;
        $computedAt = $view['computed_at'] ?? time();
        $caption = __('snap.found', ['{n}' => count($snapshots)]);
        if ($duration !== null) {
            $caption .= ' ' . __('snap.found_duration', ['{t}' => $duration]);
        }
        $caption .= ' ' . __('snap.data_at', ['{date}' => \App\Helpers\Format::date(date('c', $computedAt))]);
        ?>
        <p class="snap-caption"><?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></p>
        <table class="snapshot-table">
            <thead>
                <tr>
                    <th><?= htmlspecialchars(__('snap.id'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.host'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.added'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.tags'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snapshots as $snap): ?>
                    <?php
                    $processed = $snap['summary']['total_bytes_processed'] ?? null;
                    $added = $snap['summary']['data_added'] ?? null;
                    ?>
                    <tr id="snap-<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <td>
                            <a href="/snapshots/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
                            </a>
                        </td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($snap['hostname'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::truncate(implode(', ', $snap['paths'] ?? []), 40), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $processed !== null ? htmlspecialchars(\App\Helpers\Format::bytes((int) $processed), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><?= $added !== null ? htmlspecialchars(\App\Helpers\Format::bytes((int) $added), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td class="tag-cell" data-snap-id="<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($snap['tags'] ?? [] as $tag): ?>
                                <span class="tag-badge">
                                    <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
                                    <button class="tag-remove-btn"
                                            data-snap-id="<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"
                                            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">&times;</button>
                                </span>
                            <?php endforeach ?>
                            <div class="tag-add-row">
                                <input type="text" class="tag-input"
                                       data-snap-id="<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="<?= htmlspecialchars(__('snap.tag_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                                       size="10">
                                <button class="tag-add-btn"
                                        data-snap-id="<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('snap.tag_add'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                        </td>
                        <td>
                            <a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-browse"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a>
                            <a href="/export?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-export"><?= htmlspecialchars(__('export.export_snap'), ENT_QUOTES, 'UTF-8') ?></a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <script>
        function sendPost(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data._csrf_token) {
                    document.querySelectorAll('[data-csrf]').forEach(function(el) {
                        el.dataset.csrf = data._csrf_token;
                    });
                    // Токен одноразовый: обновляем и скрытые поля форм.
                    document.querySelectorAll('input[name="_csrf_token"]').forEach(function(el) {
                        el.value = data._csrf_token;
                    });
                }
                return data;
            });
        }

        document.querySelectorAll('.tag-add-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var snapId = this.dataset.snapId;
                var repoId = this.dataset.repoId;
                var input = document.querySelector('.tag-input[data-snap-id="' + snapId + '"]');
                var tag = input.value.trim();
                if (!tag) return;
                var csrf = this.dataset.csrf;

                var formData = new URLSearchParams();
                formData.append('repo_id', repoId);
                formData.append('snap_id', snapId);
                formData.append('tag', tag);
                formData.append('action', 'add');
                formData.append('_csrf_token', csrf);

                sendPost('/snapshots/tag', formData.toString())
                .then(function(data) {
                    if (data.ok) { window.location.reload(); }
                    else { alert(data.error || 'Error'); }
                });
            });
        });

        document.querySelectorAll('.tag-input').forEach(function(input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var btn = document.querySelector('.tag-add-btn[data-snap-id="' + this.dataset.snapId + '"]');
                    if (btn) btn.click();
                }
            });
        });

        document.querySelectorAll('.tag-remove-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var snapId = this.dataset.snapId;
                var repoId = this.dataset.repoId;
                var tag = this.dataset.tag;
                var csrf = this.dataset.csrf;

                var formData = new URLSearchParams();
                formData.append('repo_id', repoId);
                formData.append('snap_id', snapId);
                formData.append('tag', tag);
                formData.append('action', 'remove');
                formData.append('_csrf_token', csrf);

                sendPost('/snapshots/tag', formData.toString())
                .then(function(data) {
                    if (data.ok) { window.location.reload(); }
                    else { alert(data.error || 'Error'); }
                });
            });
        });
        </script>
    <?php endif ?>
<?php endif ?>
