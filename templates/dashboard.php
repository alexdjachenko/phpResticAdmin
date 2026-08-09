<h2><?= htmlspecialchars(__('dash.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if ($repoCount === 0): ?>
    <p><?= __('dash.no_repos') ?></p>
<?php elseif ($repo !== null && !empty($latestSnapshots)): ?>
    <h3><?= htmlspecialchars(__('dash.recent_snaps', ['{repo}' => $repo['name'] ?? '']), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="dashboard-snapshots">
        <table class="snapshot-table">
            <thead>
                <tr>
                    <th><?= htmlspecialchars(__('snap.id'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.date'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.paths'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latestSnapshots as $snap): ?>
                    <?php $processed = $snap['summary']['total_bytes_processed'] ?? null; ?>
                    <tr>
                        <td><a href="/snapshots/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><code><?= htmlspecialchars($snap['short_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></a></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::date($snap['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(\App\Helpers\Format::truncate(implode(', ', $snap['paths'] ?? []), 40), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $processed !== null ? htmlspecialchars(\App\Helpers\Format::bytes((int) $processed), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snap['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.view_all'), ENT_QUOTES, 'UTF-8') ?></a></p>
    </div>
<?php elseif ($repo !== null && empty($latestSnapshots)): ?>
    <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif ($repo === null && $repoCount > 0): ?>
    <p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>
