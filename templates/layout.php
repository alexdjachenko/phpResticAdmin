<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Helpers\Lang::getLocale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('app.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
<?php $taskPollInterval = (int) (\App\Core\App::configStorage()->loadSettings()['task_poll_interval'] ?? 3000); ?>
    <header>
        <div class="header-left">
            <h1><a href="/"><?= htmlspecialchars(__('app.title'), ENT_QUOTES, 'UTF-8') ?></a></h1>
            <span class="nav-links">
                <a href="/"><?= htmlspecialchars(__('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/repositories"><?= htmlspecialchars(__('nav.repositories'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if (!empty($currentRepoId ?? null)): ?>
                <a href="/snapshots"><?= htmlspecialchars(__('nav.snapshots'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if (!empty($currentRepoCanUseWrite ?? false)): ?>
                <a href="/maintenance?repo=<?= htmlspecialchars(urlencode($currentRepoId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('nav.maintenance'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/keys?repo=<?= htmlspecialchars(urlencode($currentRepoId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('nav.keys'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif ?>
                <?php endif ?>
            </span>
        </div>
        <nav>
            <?php if (!empty($repositories ?? [])): ?>
            <form method="post" action="/repositories/select" class="repo-selector">
                <select name="repo_id" onchange="this.form.submit()">
                    <option value="">-- repos --</option>
                    <?php foreach ($repositories as $repo): ?>
                        <?php
                        $cat = $repo['category'] ?? 'public';
                        $selected = (($repo['id'] ?? '') === ($currentRepoId ?? '')) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                            <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </form>
            <?php endif ?>

            <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                <a href="/tasks" class="nav-tasks"><?= htmlspecialchars(__('nav.tasks'), ENT_QUOTES, 'UTF-8') ?></a>
                <span id="task-tray" class="task-tray" hidden></span>
            <?php endif ?>

            <span class="lang-switcher">
                <?php foreach (\App\Helpers\Lang::available() as $langCode): ?>
                    <form method="post" action="/language" style="display:inline">
                        <input type="hidden" name="lang" value="<?= htmlspecialchars($langCode, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="lang-btn <?= \App\Helpers\Lang::getLocale() === $langCode ? 'active' : '' ?>">
                            <?= htmlspecialchars(strtoupper($langCode), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </form>
                <?php endforeach ?>
            </span>
            <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                <?php if (\App\Core\App::auth()->canManageUsers()): ?>
                    <a href="/users"><?= htmlspecialchars(__('nav.users'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif ?>
                <?php if (\App\Core\App::auth()->isYamlUser()): ?>
                    <a href="/account/password"><?= htmlspecialchars(__('nav.account'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif ?>
            <?php endif ?>
            <?php if (isset($isLoggedIn) && $isLoggedIn && isset($username)): ?>
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?> | <a href="/logout"><?= htmlspecialchars(__('nav.logout'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a href="/login"><?= htmlspecialchars(__('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif ?>
            <?php if (!empty($debug)): ?>
                <span class="debug-badge">DEBUG</span>
            <?php endif ?>
        </nav>
    </header>

    <?php if (!empty($flash)): ?>
        <div class="flash"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <main><?= $content ?? '' ?></main>

    <footer class="app-footer">
        © 2026 Alex Djachenko (Алексей Дьяченко)
        &middot; <strong><a href="https://github.com/alexdjachenko/phpResticAdmin" target="_blank" rel="noopener">phpResticAdmin</a></strong> v<?= htmlspecialchars($appVersion ?? 'dev', ENT_QUOTES, 'UTF-8') ?>
        &middot; restic <?= htmlspecialchars($resticVersion ?? '', ENT_QUOTES, 'UTF-8') ?>
        &middot; <a href="https://www.apache.org/licenses/LICENSE-2.0" target="_blank" rel="noopener">Apache 2.0</a>
    </footer>

    <div id="task-modal" class="task-modal" hidden>
        <div class="task-modal-backdrop" data-task-close></div>
        <div class="task-modal-window" role="dialog" aria-modal="true">
            <div class="task-modal-head">
                <strong id="task-modal-title"></strong>
                <span id="task-modal-state" class="task-state"></span>
                <button type="button" id="task-modal-close" class="task-modal-close" data-task-close aria-label="close">&times;</button>
            </div>
            <pre id="task-modal-output" class="task-output"></pre>
            <div class="task-modal-actions">
                <button type="button" id="task-modal-promote" hidden><?= htmlspecialchars(__('tasks.promote'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" id="task-modal-cancel"><?= htmlspecialchars(__('tasks.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" id="task-modal-refresh" hidden><?= htmlspecialchars(__('tasks.refresh_data'), ENT_QUOTES, 'UTF-8') ?></button>
                <a id="task-modal-full" href="#" class="task-modal-full"><?= htmlspecialchars(__('tasks.full_output'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </div>

    <script>
    window.TaskUI = (function () {
        var pollInterval = <?= (int) $taskPollInterval ?>;
        var states = {
            queued: <?= json_encode(__('tasks.state_queued')) ?>,
            running: <?= json_encode(__('tasks.state_running')) ?>,
            finished: <?= json_encode(__('tasks.state_finished')) ?>,
            skipped: <?= json_encode(__('tasks.state_skipped')) ?>,
            unknown: <?= json_encode(__('tasks.state_unknown')) ?>
        };
        var t = {
            active: <?= json_encode(__('tasks.active')) ?>,
            queued: <?= json_encode(__('tasks.queued_position')) ?>,
            loading: <?= json_encode(__('tasks.loading')) ?>,
            streamError: <?= json_encode(__('tasks.stream_error')) ?>,
            confirmCancel: <?= json_encode(__('tasks.confirm_cancel')) ?>
        };

        var tray = document.getElementById('task-tray');
        var modal = document.getElementById('task-modal');
        var titleEl = document.getElementById('task-modal-title');
        var stateEl = document.getElementById('task-modal-state');
        var outEl = document.getElementById('task-modal-output');
        var cancelBtn = document.getElementById('task-modal-cancel');
        var promoteBtn = document.getElementById('task-modal-promote');
        var refreshBtn = document.getElementById('task-modal-refresh');
        var fullLink = document.getElementById('task-modal-full');

        if (!modal) { return { open: function () {} }; }

        var currentLabel = null;
        var statusTimer = null;
        var trayTimer = null;
        var streamSeq = 0;

        function updateCsrf(token) {
            if (!token) { return; }
            document.querySelectorAll('[data-csrf]').forEach(function (el) { el.dataset.csrf = token; });
            document.querySelectorAll('input[name="_csrf_token"]').forEach(function (el) { el.value = token; });
        }

        function stateText(state) {
            return states[state] || state;
        }

        function pollTray() {
            if (document.visibilityState !== 'visible') { return; }
            fetch('/tasks/active', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data._csrf_token) { updateCsrf(data._csrf_token); }
                    renderTray((data && data.tasks) ? data.tasks : []);
                })
                .catch(function () {});
        }

        function renderTray(tasks) {
            if (!tray) { return; }
            var active = tasks.filter(function (job) {
                return job.state === 'queued' || job.state === 'running';
            });
            if (active.length === 0) {
                tray.hidden = true;
                tray.innerHTML = '';
                return;
            }
            tray.hidden = false;
            tray.innerHTML = '';
            active.forEach(function (job) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'task-chip task-chip-' + job.state;
                var label = job.title || job.label || ('#' + job.id);
                var text = label + ' \u00b7 ' + stateText(job.state);
                if (job.state === 'queued' && job.position) {
                    text += ' (' + t.queued.replace('{n}', job.position) + ')';
                }
                btn.textContent = text;
                btn.addEventListener('click', function () {
                    open(job.label, job.title || job.label);
                });
                tray.appendChild(btn);
            });
        }

        function startStatusPolling(label) {
            stopStatusPolling();
            statusTimer = setInterval(function () {
                if (document.visibilityState !== 'visible') { return; }
                fetch('/tasks/status?label=' + encodeURIComponent(label), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data._csrf_token) { updateCsrf(data._csrf_token); }
                        if (!data || !data.ok) { return; }
                        if (label !== currentLabel) { return; }
                        stateEl.textContent = stateText(data.state) + (data.position ? ' (' + t.queued.replace('{n}', data.position) + ')' : '');
                        if (data.state === 'finished') {
                            cancelBtn.hidden = true;
                            promoteBtn.hidden = true;
                            refreshBtn.hidden = false;
                            stopStatusPolling();
                        } else {
                            promoteBtn.hidden = data.state !== 'queued';
                        }
                    })
                    .catch(function () {});
            }, pollInterval);
        }

        function stopStatusPolling() {
            if (statusTimer !== null) {
                clearInterval(statusTimer);
                statusTimer = null;
            }
        }

        function stream(label) {
            streamSeq++;
            var seq = streamSeq;
            outEl.textContent = '';
            fetch('/tasks/stream?label=' + encodeURIComponent(label))
                .then(function (resp) {
                    if (!resp.ok || !resp.body) { throw new Error('stream'); }
                    var reader = resp.body.getReader();
                    var decoder = new TextDecoder();
                    function pump() {
                        reader.read().then(function (chunk) {
                            if (seq !== streamSeq) { return; }
                            if (chunk.done) { return; }
                            outEl.textContent += decoder.decode(chunk.value, { stream: true });
                            outEl.scrollTop = outEl.scrollHeight;
                            pump();
                        }, function () {});
                    }
                    pump();
                })
                .catch(function () {
                    if (seq !== streamSeq) { return; }
                    outEl.textContent += '\n' + t.streamError;
                });
        }

        function open(label, title) {
            if (!label) { return; }
            currentLabel = label;
            titleEl.textContent = title || label;
            stateEl.textContent = '';
            cancelBtn.hidden = false;
            promoteBtn.hidden = true;
            refreshBtn.hidden = true;
            fullLink.href = '/tasks/view?label=' + encodeURIComponent(label);
            modal.hidden = false;
            stream(label);
            startStatusPolling(label);
        }

        function close() {
            modal.hidden = true;
            stopStatusPolling();
            currentLabel = null;
            streamSeq++;
        }

        function post(url, label) {
            var body = new URLSearchParams();
            body.append('label', label);
            body.append('_csrf_token', currentCsrf());
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data._csrf_token) { updateCsrf(data._csrf_token); }
                return data;
            });
        }

        function currentCsrf() {
            var el = document.querySelector('[data-csrf]');
            return el ? el.dataset.csrf : '';
        }

        document.querySelectorAll('[data-task-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (!currentLabel || !window.confirm(t.confirmCancel)) { return; }
                post('/tasks/cancel', currentLabel).then(function (data) {
                    if (!data || !data.ok) { window.alert((data && data.error) || t.streamError); }
                }).catch(function () {});
            });
        }

        if (promoteBtn) {
            promoteBtn.addEventListener('click', function () {
                if (!currentLabel) { return; }
                post('/tasks/promote', currentLabel).then(function () {}).catch(function () {});
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { window.location.reload(); });
        }

        document.querySelectorAll('form[data-ajax-task]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var url = form.getAttribute('action') || window.location.pathname;
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new URLSearchParams(new FormData(form)).toString()
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data._csrf_token) { updateCsrf(data._csrf_token); }
                    if (data && data.ok && data.label) {
                        open(data.label, data.title || data.label);
                    } else {
                        window.alert((data && data.error) || t.streamError);
                    }
                })
                .catch(function () { window.alert(t.streamError); });
            });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') { pollTray(); }
        });

        pollTray();
        trayTimer = setInterval(pollTray, pollInterval);

        return {
            open: open,
            close: close,
            updateCsrf: updateCsrf
        };
    })();
    </script>
    </body>
</html>
