<h2><?= htmlspecialchars(__('dash.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<div class="dashboard-stats">
    <table class="repo-info">
        <tr>
            <th><?= htmlspecialchars(__('dash.repo_stats'), ENT_QUOTES, 'UTF-8') ?></th>
            <td>
                <?= htmlspecialchars(__('dash.public'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($repoStats['public'] ?? 0) ?>
                &middot; <?= htmlspecialchars(__('dash.private'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($repoStats['private'] ?? 0) ?>
                &middot; <?= htmlspecialchars(__('dash.session'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($repoStats['session'] ?? 0) ?>
                &middot; <?= htmlspecialchars(__('dash.total'), ENT_QUOTES, 'UTF-8') ?>: <?= (int) ($repoStats['total'] ?? 0) ?>
            </td>
        </tr>
    </table>
</div>

<?php if (!empty($activeTasks)): ?>
    <h3><?= htmlspecialchars(__('dash.active_tasks'), ENT_QUOTES, 'UTF-8') ?></h3>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= htmlspecialchars(__('dash.task_state'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('dash.task_label'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('dash.task_command'), ENT_QUOTES, 'UTF-8') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activeTasks as $task): ?>
                <tr>
                    <td><?= (int) ($task['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(__('tasks.state_' . ($task['state'] ?? 'unknown')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($task['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::truncate($task['command'] ?? '', 60), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($task['label'])): ?>
                            <a href="/tasks/stream?label=<?= htmlspecialchars(urlencode($task['label']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.task_open'), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if (!empty($recentTasks)): ?>
    <h3><?= htmlspecialchars(__('dash.recent_tasks'), ENT_QUOTES, 'UTF-8') ?></h3>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= htmlspecialchars(__('dash.task_state'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('dash.task_label'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('dash.task_command'), ENT_QUOTES, 'UTF-8') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentTasks as $task): ?>
                <tr>
                    <td><?= (int) ($task['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(__('tasks.state_' . ($task['state'] ?? 'unknown')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($task['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::truncate($task['command'] ?? '', 60), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($task['label'])): ?>
                            <a href="/tasks/stream?label=<?= htmlspecialchars(urlencode($task['label']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.task_open'), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if (empty($activeTasks) && empty($recentTasks)): ?>
    <p><?= htmlspecialchars(__('dash.no_tasks'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

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
