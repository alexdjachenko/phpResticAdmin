<h2><?= htmlspecialchars(__('dash.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if ($repoCount === 0): ?>
    <p><?= __('dash.no_repos') ?></p>
<?php elseif ($repo === null): ?>
    <p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <h3><?= __('dash.recent_snaps', ['{repo}' => htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8')]) ?></h3>

    <?php if (empty($recentSnapshots)): ?>
        <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <table class="snapshot-table dashboard-snapshots">
            <thead>
                <tr>
                    <th><?= htmlspecialchars(__('snap.id'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSnapshots as $snap): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::truncate(implode(', ', $snap['paths'] ?? []), 40), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::bytes((int) ($snap['summary']['total_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.view_all'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php endif ?>
<?php endif ?>
