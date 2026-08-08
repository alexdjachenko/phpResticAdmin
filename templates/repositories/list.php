<h2>Repositories</h2>

<?php if (empty($repositories)): ?>
    <p>No repositories configured.</p>
<?php else: ?>
    <table class="repo-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Path</th>
                <th>Status</th>
                <?php if ($isLoggedIn): ?>
                    <th></th>
                <?php endif ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repositories as $repo): ?>
                <tr id="repo-<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($repo['type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($repo['path'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td class="repo-status" data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">-</td>
                    <?php if ($isLoggedIn): ?>
                        <td>
                            <button class="btn-check"
                                    data-repo-id="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-csrf="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                Check
                            </button>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <?php if ($isLoggedIn): ?>
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
            return data;
        });
    }

    document.querySelectorAll('.btn-check').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var repoId = this.dataset.repoId;
            var statusCell = document.querySelector('.repo-status[data-repo-id="' + repoId + '"]');

            statusCell.textContent = 'Checking...';
            statusCell.className = 'repo-status checking';

            var formData = new URLSearchParams();
            formData.append('repo_id', repoId);
            formData.append('_csrf_token', this.dataset.csrf);

            sendPost('/repositories/check', formData.toString(), this)
            .then(function(data) {
                if (data.ok) {
                    statusCell.textContent = 'OK';
                    statusCell.className = 'repo-status ok';
                } else {
                    statusCell.textContent = data.error || 'Error';
                    statusCell.className = 'repo-status error';
                }
            })
            .catch(function(err) {
                statusCell.textContent = 'Request failed';
                statusCell.className = 'repo-status error';
            });
        });
    });
    <?php if (!empty($debug) && $isLoggedIn): ?>
    // Cache invalidation
    var csrfCache = '<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>';

    document.getElementById('btn-cache-invalidate').addEventListener('click', function() {
        var btnEl = this;
        var originalText = btnEl.textContent;
        var originalClass = btnEl.className;

        btnEl.textContent = 'Clearing...';
        btnEl.disabled = true;

        var formData = new URLSearchParams();
        formData.append('_csrf_token', csrfCache);

        sendPost('/cache/invalidate', formData.toString(), null)
        .then(function(data) {
            if (data._csrf_token) csrfCache = data._csrf_token;

            if (data.ok) {
                btnEl.textContent = 'OK, cleared ' + data.count + ' scripts';
                btnEl.className = 'btn-cache btn-cache-ok';
            } else {
                btnEl.textContent = data.error || 'Error';
                btnEl.className = 'btn-cache btn-cache-error';
            }
        })
        .catch(function() {
            btnEl.textContent = 'Network error';
            btnEl.className = 'btn-cache btn-cache-error';
        })
        .finally(function() {
            btnEl.disabled = false;

            // Reset to original after 3 seconds
            setTimeout(function() {
                btnEl.textContent = originalText;
                btnEl.className = originalClass;
            }, 3000);
        });
    });
    <?php endif ?>
    </script>
    <?php endif ?>
<?php endif ?>

<?php if (!empty($debug) && $isLoggedIn): ?>
<hr class="debug-sep">
<div class="debug-panel">
    <h3>Debug</h3>
    <p>Debug level: <strong><?= \App\Core\App::debugLevel() ?></strong></p>
    <button id="btn-cache-invalidate" class="btn-cache">Invalidate OPcache</button>
</div>
<?php endif ?>
