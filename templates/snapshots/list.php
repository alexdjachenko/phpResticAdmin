<?php if ($repo !== null): ?>
<h2>
    <?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?>
    <span class="snap-repo-name">— <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
</h2>
<p><code><?= htmlspecialchars($repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></p>
<?php else: ?>
<h2><?= htmlspecialchars(__('snap.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if (empty($snapshots)): ?>
    <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('snap.id'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.host'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('snap.tags'), ENT_QUOTES, 'UTF-8') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($snapshots as $snap): ?>
                <tr id="snap-<?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td><code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($snap['hostname'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::truncate(implode(', ', $snap['paths'] ?? []), 40), ENT_QUOTES, 'UTF-8') ?></td>
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
            }
            return data;
        });
    }

    function updateTagCsrf(csrf) {
        document.querySelectorAll('.tag-input, .tag-add-btn, .tag-remove-btn').forEach(function(el) {
            el.dataset.csrf = csrf;
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
                if (data._csrf_token) updateTagCsrf(data._csrf_token);
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
                if (data._csrf_token) updateTagCsrf(data._csrf_token);
                if (data.ok) { window.location.reload(); }
                else { alert(data.error || 'Error'); }
            });
        });
    });
    </script>
<?php endif ?>
