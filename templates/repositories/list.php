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
    document.querySelectorAll('.btn-check').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var repoId = this.dataset.repoId;
            var csrf = this.dataset.csrf;
            var statusCell = document.querySelector('.repo-status[data-repo-id="' + repoId + '"]');

            statusCell.textContent = 'Checking...';
            statusCell.className = 'repo-status checking';

            var formData = new URLSearchParams();
            formData.append('repo_id', repoId);
            formData.append('_csrf_token', csrf);

            fetch('/repositories/check', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                // Update CSRF token for subsequent checks
                if (data._csrf_token) {
                    btn.dataset.csrf = data._csrf_token;
                }

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
    </script>
    <?php endif ?>
<?php endif ?>
