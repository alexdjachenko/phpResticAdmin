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
                <th><?= htmlspecialchars(__('repo.category'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repositories as $repo): ?>
                <?php
                $cat = $repo['category'] ?? 'public';
                $catLabel = __('repo.category.' . $cat);
                $badgeClass = 'badge-' . $cat;
                ?>
                <tr>
                    <td><a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= htmlspecialchars($repo['type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="category-badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
