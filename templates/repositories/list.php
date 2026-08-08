<h2><?= htmlspecialchars(__('repo.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($canAdd)): ?>
    <p><a href="/repositories/add" class="btn-add">+ <?= htmlspecialchars(__('repo.add'), ENT_QUOTES, 'UTF-8') ?></a></p>
<?php endif ?>

<?php if (empty($repositories)): ?>
    <p><?= htmlspecialchars(__('repo.no_repos'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <table class="repo-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('repo.name'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('repo.type'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('repo.path'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('repo.category'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('repo.status'), ENT_QUOTES, 'UTF-8') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repositories as $repo): ?>
                <?php
                $cat = $repo['category'] ?? 'public';
                $catLabel = __('repo.category.' . $cat);
                $badgeClass = 'badge-' . $cat;
                $icon = $cat === 'public' ? '' : ($cat === 'private' ? '' : '');
                ?>
                <tr id="repo-<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($repo['type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><span class="category-badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="repo-status" data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">-</td>
                    <td class="repo-actions">
                        <?php if ($isLoggedIn): ?>
                            <button class="btn-check"
                                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(__('repo.check'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endif ?>
                        <?php if (!empty($repo['canMove']) && !empty($categories) && count($categories) > 1): ?>
                            <select class="move-dropdown"
                                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <option value=""><?= htmlspecialchars(__('repo.move_to'), ENT_QUOTES, 'UTF-8') ?>...</option>
                                <?php foreach ($categories as $catKey => $catLabel): ?>
                                    <?php if ($catKey !== $cat): ?>
                                        <option value="<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endif ?>
                                <?php endforeach ?>
                            </select>
                        <?php endif ?>
                        <?php if (!empty($repo['canDelete'])): ?>
                            <button class="btn-delete"
                                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(__('repo.delete'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

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
            // Update all CSRF data attributes on the page
            if (data._csrf_token) {
                document.querySelectorAll('[data-csrf]').forEach(function(el) {
                    el.dataset.csrf = data._csrf_token;
                });
            }
            return data;
        });
    }

    // Check buttons
    document.querySelectorAll('.btn-check').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var repoId = this.dataset.repoId;
            var statusCell = document.querySelector('.repo-status[data-repo-id="' + repoId + '"]');

            statusCell.textContent = <?= json_encode(__('repo.status_checking')) ?>;
            statusCell.className = 'repo-status checking';

            var formData = new URLSearchParams();
            formData.append('repo_id', repoId);
            formData.append('_csrf_token', this.dataset.csrf);

            sendPost('/repositories/check', formData.toString(), this)
            .then(function(data) {
                if (data.ok) {
                    statusCell.textContent = <?= json_encode(__('repo.status_ok')) ?>;
                    statusCell.className = 'repo-status ok';
                } else {
                    statusCell.textContent = data.error || <?= json_encode(__('repo.status_error')) ?>;
                    statusCell.className = 'repo-status error';
                }
            })
            .catch(function(err) {
                statusCell.textContent = <?= json_encode(__('repo.status_failed')) ?>;
                statusCell.className = 'repo-status error';
            });
        });
    });

    // Move dropdowns
    document.querySelectorAll('.move-dropdown').forEach(function(select) {
        select.addEventListener('change', function() {
            var toCategory = this.value;
            if (!toCategory) return;

            var repoId = this.dataset.repoId;

            var formData = new URLSearchParams();
            formData.append('repo_id', repoId);
            formData.append('to_category', toCategory);
            formData.append('_csrf_token', this.dataset.csrf);

            var originalValue = this.value;

            sendPost('/repositories/move', formData.toString(), this)
            .then(function(data) {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error');
                }
            })
            .catch(function(err) {
                alert('Network error');
            });
        });
    });

    // Delete buttons
    document.querySelectorAll('.btn-delete').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(<?= json_encode(__('repo.confirm_delete')) ?>)) return;

            var repoId = this.dataset.repoId;

            var formData = new URLSearchParams();
            formData.append('repo_id', repoId);
            formData.append('_csrf_token', this.dataset.csrf);

            sendPost('/repositories/delete', formData.toString(), this)
            .then(function(data) {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error');
                }
            })
            .catch(function(err) {
                alert('Network error');
            });
        });
    });
    </script>
<?php endif ?>

<?php if (!empty($debug) && $isLoggedIn): ?>
<hr class="debug-sep">
<div class="debug-panel">
    <h3>Debug</h3>
    <p>Debug level: <strong><?= \App\Core\App::debugLevel() ?></strong></p>
    <button id="btn-cache-invalidate" class="btn-cache">Invalidate OPcache</button>
</div>
<script>
(function() {
    var btn = document.getElementById('btn-cache-invalidate');
    if (!btn) return;

    var csrfCache = <?= json_encode($csrfToken ?? '') ?>;

    btn.addEventListener('click', function() {
        var originalText = btn.textContent;
        var originalClass = btn.className;

        btn.textContent = 'Clearing...';
        btn.disabled = true;

        var formData = new URLSearchParams();
        formData.append('_csrf_token', csrfCache);

        sendPost('/cache/invalidate', formData.toString(), null)
        .then(function(data) {
            if (data._csrf_token) csrfCache = data._csrf_token;

            if (data.ok) {
                btn.textContent = 'OK, cleared ' + data.count + ' scripts';
                btn.className = 'btn-cache btn-cache-ok';
            } else {
                btn.textContent = data.error || 'Error';
                btn.className = 'btn-cache btn-cache-error';
            }
        })
        .catch(function() {
            btn.textContent = 'Network error';
            btn.className = 'btn-cache btn-cache-error';
        })
        .finally(function() {
            btn.disabled = false;
            setTimeout(function() {
                btn.textContent = originalText;
                btn.className = originalClass;
            }, 3000);
        });
    });
})();
</script>
<?php endif ?>
