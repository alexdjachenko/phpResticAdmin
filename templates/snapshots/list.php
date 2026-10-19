<?php
/**
 * @var mixed $repo
 * @var mixed $view
 */
$state = $view['state'] ?? 'absent';
$label = (string) ($view['promise'] ?? '');
$duration = is_array($view['meta'] ?? null) ? ($view['meta']['duration'] ?? null) : null;
?>
<?php if ($repo !== null): ?>
<div class="breadcrumb">
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2>
    <?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?>
    <span class="snap-repo-name">— <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
</h2>
<?php
$locationField = match ($repo['type'] ?? 'local') {
    's3' => 's3_bucket',
    'sftp' => 'sftp_path',
    'rest' => 'rest_url',
    default => 'local_path',
};
$repoLocation = (string) ($repo[$locationField] ?? $repo['path'] ?? '');
?>
<p class="snap-repo-path"><code><?= htmlspecialchars($repoLocation, ENT_QUOTES, 'UTF-8') ?></code></p>
<?php else: ?>
<h2><?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if ($repo !== null && in_array($state, ['waiting', 'ready_to_process'], true)): ?>
    <?php
    // Идёт задача или остался разбор её вывода: одна кнопка-индикатор,
    // клик открывает единую модалку с живым выводом и статусом постобработки.
    if ($state === 'ready_to_process') {
        $stateText = __('tasks.proc_processing');
    } else {
        $taskState = (string) ($view['task_state'] ?? 'queued');
        $stateText = __('tasks.state_' . $taskState);
        if (!empty($view['position'])) {
            $stateText .= ' (' . __('tasks.queued_position', ['{n}' => $view['position']]) . ')';
        }
    }
    $taskTitle = __('tasks.op_snapshots') . ' — ' . (string) ($repo['name'] ?? '');
    ?>
    <p class="snap-progress">
        <button type="button" class="btn-snapshots btn-snap-busy"
                data-task-open
                data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                data-title="<?= htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') ?>">
            <span class="snap-progress-state"><?= htmlspecialchars($stateText, ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </p>
    <?php if ($state === 'ready_to_process'): ?>
    <script>
    /*
     * Вывод задачи готов, осталось разобрать его. GET /snapshots сам вызывает
     * reconcile(); один guarded reload доводит страницу до ready и не даёт
     * цикла перезагрузок, если разбор не удался (тогда покажется ошибка).
     */
    (function () {
        var key = 'snap-reconcile:' + <?= json_encode($label) ?>;
        try {
            if (sessionStorage.getItem(key) === '1') { sessionStorage.removeItem(key); return; }
            sessionStorage.setItem(key, '1');
        } catch (e) { /* приватный режим: без guard делаем один reload */ }
        setTimeout(function () { window.location.reload(); }, 300);
    })();
    </script>
    <?php endif ?>
<?php elseif ($repo !== null): ?>
    <form method="post" action="/snapshots/load" data-ajax-task>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-snapshots"><?= htmlspecialchars($state === 'ready' ? __('snap.refresh') : __('snap.load_button'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>

    <?php if ($state === 'failed' && !empty($view['error'])): ?>
        <p class="flash flash-error"><?= htmlspecialchars($view['error'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif ?>

    <?php if ($state === 'ready'): ?>
        <?php
        $snapshots = is_array($snapshots ?? null) ? $snapshots : (is_array($view['value'] ?? null) ? $view['value'] : []);
        $computedAt = (int) ($view['cached_at'] ?? time());
        $snapTags = is_array($snapTags ?? null) ? $snapTags : [];
        $snapHosts = is_array($snapHosts ?? null) ? $snapHosts : [];
        $snapPaths = is_array($snapPaths ?? null) ? $snapPaths : [];
        ?>
        <?php if (empty($snapshots)): ?>
            <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <?php
            $caption = __('snap.found', ['{n}' => (string) count($snapshots)]);
            if ($duration !== null) {
                $caption .= ' ' . __('snap.found_duration', ['{t}' => $duration]);
            }
            $caption .= ' ' . __('snap.data_at', ['{date}' => \App\Helpers\Format::date(date('c', $computedAt))]);
            ?>
            <p class="snap-caption"><?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="snap-filters">
                <label class="snap-filter snap-filter-search">
                    <span><?= htmlspecialchars(__('snap.filter_search'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="search" id="snap-q" placeholder="<?= htmlspecialchars(__('snap.filter_search_ph'), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <?php if (!empty($snapTags)): ?>
                <label class="snap-filter snap-filter-tag">
                    <span><?= htmlspecialchars(__('snap.filter_tag'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="snap-tag">
                        <option value=""><?= htmlspecialchars(__('snap.filter_any'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($snapTags as $tag): ?>
                            <option value="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <?php endif ?>

                <?php if (!empty($snapHosts)): ?>
                <label class="snap-filter snap-filter-host">
                    <span><?= htmlspecialchars(__('snap.filter_host'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="snap-host">
                        <option value=""><?= htmlspecialchars(__('snap.filter_any'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($snapHosts as $host): ?>
                            <option value="<?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <?php endif ?>

                <?php if (!empty($snapPaths)): ?>
                <label class="snap-filter snap-filter-path">
                    <span><?= htmlspecialchars(__('snap.filter_path'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="snap-path">
                        <option value=""><?= htmlspecialchars(__('snap.filter_any'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($snapPaths as $path): ?>
                            <option value="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(\App\Helpers\Format::truncate($path, 60), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <?php endif ?>

                <label class="snap-filter snap-filter-date">
                    <span><?= htmlspecialchars(__('snap.filter_from'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="date" id="snap-from">
                </label>
                <label class="snap-filter snap-filter-date">
                    <span><?= htmlspecialchars(__('snap.filter_to'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="date" id="snap-to">
                </label>

                <label class="snap-filter snap-filter-sort">
                    <span><?= htmlspecialchars(__('snap.sort'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="snap-sort">
                        <option value="date-desc" selected><?= htmlspecialchars(__('snap.sort_date_desc'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="date-asc"><?= htmlspecialchars(__('snap.sort_date_asc'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="size-desc"><?= htmlspecialchars(__('snap.sort_size_desc'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <button type="button" class="snap-filter-reset" id="snap-reset"><?= htmlspecialchars(__('snap.filter_reset'), ENT_QUOTES, 'UTF-8') ?></button>
                <span class="snap-filter-counter" id="snap-counter"></span>
            </div>

            <p class="snap-empty-filter" id="snap-empty" hidden><?= htmlspecialchars(__('snap.filter_empty'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="snap-list" id="snap-list">
            <?php foreach ($snapshots as $snap): ?>
                <?php
                $shortId = (string) ($snap['short_id'] ?? '');
                $snapId = (string) ($snap['id'] ?? '');
                $hostname = (string) ($snap['hostname'] ?? '');
                $timeIso = (string) ($snap['time'] ?? '');
                $paths = array_map('strval', is_array($snap['paths'] ?? null) ? $snap['paths'] : []);
                $tags = array_map('strval', is_array($snap['tags'] ?? null) ? $snap['tags'] : []);
                $processed = $snap['summary']['total_bytes_processed'] ?? null;
                $added = $snap['summary']['data_added'] ?? null;
                $visiblePaths = array_slice($paths, 0, 3);
                $hiddenPaths = array_slice($paths, 3);
                $jsonOpts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                ?>
                <article class="snap-card"
                         id="snap-<?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?>"
                         data-snap-id="<?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?>"
                         data-host="<?= htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') ?>"
                         data-time="<?= htmlspecialchars($timeIso, ENT_QUOTES, 'UTF-8') ?>"
                         data-size="<?= (int) ($processed ?? 0) ?>"
                         data-tags="<?= htmlspecialchars(json_encode($tags, $jsonOpts), ENT_QUOTES, 'UTF-8') ?>"
                         data-paths="<?= htmlspecialchars(json_encode($paths, $jsonOpts), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="snap-card-main">
                        <header class="snap-card-head">
                            <a class="snap-card-id" href="/snapshots/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>">
                                <code><?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?></code>
                            </a>
                            <time class="snap-card-date" datetime="<?= htmlspecialchars($timeIso, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($timeIso, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(\App\Helpers\Format::date($timeIso), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                            <dl class="snap-meta">
                                <div><dt><?= htmlspecialchars(__('snap.host'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($hostname !== '' ? $hostname : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                <div><dt><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $processed !== null ? htmlspecialchars(\App\Helpers\Format::bytes((int) $processed), ENT_QUOTES, 'UTF-8') : '—' ?></dd></div>
                                <div><dt><?= htmlspecialchars(__('snap.added'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $added !== null ? htmlspecialchars(\App\Helpers\Format::bytes((int) $added), ENT_QUOTES, 'UTF-8') : '—' ?></dd></div>
                            </dl>
                        </header>

                        <?php if (!empty($visiblePaths)): ?>
                        <div class="snap-card-paths">
                            <ul class="snap-paths">
                                <?php foreach ($visiblePaths as $p): ?>
                                    <li><code><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></code></li>
                                <?php endforeach ?>
                            </ul>
                            <?php if (!empty($hiddenPaths)): ?>
                            <details class="snap-paths-more">
                                <summary><?= htmlspecialchars(__('snap.paths_more', ['{n}' => (string) count($hiddenPaths)]), ENT_QUOTES, 'UTF-8') ?></summary>
                                <ul class="snap-paths">
                                    <?php foreach ($hiddenPaths as $p): ?>
                                        <li><code><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></code></li>
                                    <?php endforeach ?>
                                </ul>
                            </details>
                            <?php endif ?>
                        </div>
                        <?php endif ?>

                        <div class="snap-card-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="tag-badge">
                                    <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
                                    <button class="tag-remove-btn"
                                            data-snap-id="<?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?>"
                                            data-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"
                                            data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">&times;</button>
                                </span>
                            <?php endforeach ?>
                            <div class="tag-add-row">
                                <input type="text" class="tag-input"
                                       data-snap-id="<?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?>"
                                       data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="<?= htmlspecialchars(__('snap.tag_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                                       size="10">
                                <button class="tag-add-btn"
                                        data-snap-id="<?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?>"
                                        data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('snap.tag_add'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                        </div>
                    </div>

                    <footer class="snap-card-actions">
                        <a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>" class="btn-browse"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="/export?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>" class="btn-export"><?= htmlspecialchars(__('export.export_snap'), ENT_QUOTES, 'UTF-8') ?></a>
                    </footer>
                </article>
            <?php endforeach ?>
            </div>

            <script>
            (function () {
                var list = document.getElementById('snap-list');
                if (!list) { return; }
                var cards = Array.prototype.slice.call(list.querySelectorAll('.snap-card'));
                if (cards.length === 0) { return; }

                var qEl = document.getElementById('snap-q');
                var tagEl = document.getElementById('snap-tag');
                var hostEl = document.getElementById('snap-host');
                var pathEl = document.getElementById('snap-path');
                var fromEl = document.getElementById('snap-from');
                var toEl = document.getElementById('snap-to');
                var sortEl = document.getElementById('snap-sort');
                var resetEl = document.getElementById('snap-reset');
                var counter = document.getElementById('snap-counter');
                var emptyEl = document.getElementById('snap-empty');
                var tpl = <?= json_encode(__('snap.filter_counter')) ?>;

                // Разбираем JSON-атрибуты и время один раз — фильтр не парсит на каждое нажатие.
                var items = cards.map(function (card) {
                    var parse = function (name) {
                        try { return JSON.parse(card.dataset[name] || '[]'); } catch (e) { return []; }
                    };
                    var tags = parse('tags'), paths = parse('paths');
                    var ts = Date.parse(card.dataset.time || '');
                    return {
                        el: card,
                        tagsLower: tags.map(function (t) { return String(t).toLowerCase(); }),
                        paths: paths,
                        host: card.dataset.host || '',
                        ts: isNaN(ts) ? 0 : ts,
                        size: parseInt(card.dataset.size || '0', 10) || 0,
                        haystack: [card.dataset.snapId || '', card.dataset.host || '']
                            .concat(paths, tags).join(' ').toLowerCase()
                    };
                });

                function dayStart(v) { return v ? new Date(v + 'T00:00:00').getTime() : null; }
                function dayEnd(v) { return v ? new Date(v + 'T23:59:59.999').getTime() : null; }

                function apply() {
                    var q = (qEl && qEl.value || '').trim().toLowerCase();
                    var tag = tagEl ? tagEl.value.toLowerCase() : '';
                    var host = hostEl ? hostEl.value : '';
                    var path = pathEl ? pathEl.value : '';
                    var from = dayStart(fromEl && fromEl.value);
                    var to = dayEnd(toEl && toEl.value);

                    var shown = 0;
                    items.forEach(function (it) {
                        var ok = true;
                        if (q !== '' && it.haystack.indexOf(q) === -1) { ok = false; }
                        if (ok && tag !== '' && it.tagsLower.indexOf(tag) === -1) { ok = false; }
                        if (ok && host !== '' && it.host !== host) { ok = false; }
                        if (ok && path !== '' && it.paths.indexOf(path) === -1) { ok = false; }
                        if (ok && from !== null && it.ts < from) { ok = false; }
                        if (ok && to !== null && it.ts > to) { ok = false; }
                        it.el.hidden = !ok;
                        if (ok) { shown++; }
                    });

                    if (emptyEl) { emptyEl.hidden = shown !== 0; }
                    if (counter) {
                        counter.textContent = tpl.replace('{shown}', shown).replace('{total}', items.length);
                    }
                }

                function sortBy() {
                    var mode = sortEl ? sortEl.value : 'date-desc';
                    var sorted = items.slice().sort(function (a, b) {
                        if (mode === 'date-asc') { return a.ts - b.ts; }
                        if (mode === 'size-desc') { return b.size - a.size || b.ts - a.ts; }
                        return b.ts - a.ts; // date-desc — по умолчанию
                    });
                    // Порядок в списке = порядок в DOM; скрытые карточки переставляются вместе со всеми.
                    sorted.forEach(function (it) { list.appendChild(it.el); });
                }

                [qEl, tagEl, hostEl, pathEl, fromEl, toEl].forEach(function (el) {
                    if (!el) { return; }
                    el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', apply);
                });
                if (sortEl) { sortEl.addEventListener('change', sortBy); }

                if (resetEl) {
                    resetEl.addEventListener('click', function () {
                        [qEl, tagEl, hostEl, pathEl, fromEl, toEl].forEach(function (el) {
                            if (el) { el.value = ''; }
                        });
                        if (sortEl) { sortEl.value = 'date-desc'; sortBy(); }
                        apply();
                    });
                }

                apply();
            })();
            </script>

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
<?php endif ?>
