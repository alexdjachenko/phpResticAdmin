<?php
/**
 * @var mixed $repoCount
 * @var mixed $repo
 */
?>
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
    <div class="table-scroll">
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
                    <td><?= htmlspecialchars($task['title'] ?? ($task['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::truncate($task['command'] ?? '', 60), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($task['label'])): ?>
                            <a href="/tasks/view?label=<?= htmlspecialchars(urlencode($task['label']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.task_open'), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
        </table>
        </div>
        <?php endif ?>

<?php if (!empty($recentTasks)): ?>
    <h3><?= htmlspecialchars(__('dash.recent_tasks'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="table-scroll">
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
                    <td><?= htmlspecialchars($task['title'] ?? ($task['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::truncate($task['command'] ?? '', 60), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($task['label'])): ?>
                            <a href="/tasks/view?label=<?= htmlspecialchars(urlencode($task['label']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.task_open'), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
        </table>
        </div>
        <?php endif ?>

<?php if (empty($activeTasks) && empty($recentTasks)): ?>
    <p><?= htmlspecialchars(__('dash.no_tasks'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if ($repoCount === 0): ?>
    <p><?= __('dash.no_repos') ?></p>
<?php elseif ($repo !== null && !empty($needLoad)): ?>
    <p>
        <a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('snap.load_button'), ENT_QUOTES, 'UTF-8') ?></a>
    </p>
<?php elseif ($repo !== null && !empty($latestSnapshots)): ?>
    <h3><?= htmlspecialchars(__('dash.recent_snaps', ['{repo}' => $repo['name'] ?? '']), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="dashboard-snapshots">
        <div class="snap-list">
        <?php foreach ($latestSnapshots as $snap): ?>
            <?php
            $shortId = (string) ($snap['short_id'] ?? '');
            $snapId = (string) ($snap['id'] ?? '');
            $timeIso = (string) ($snap['time'] ?? '');
            $paths = array_map('strval', is_array($snap['paths'] ?? null) ? $snap['paths'] : []);
            $processed = $snap['summary']['total_bytes_processed'] ?? null;
            ?>
            <article class="snap-card snap-card--compact">
                <div class="snap-card-main">
                    <header class="snap-card-head">
                        <a class="snap-card-id" href="/snapshots/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>">
                            <code><?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?></code>
                        </a>
                        <time class="snap-card-date" datetime="<?= htmlspecialchars($timeIso, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(\App\Helpers\Format::date($timeIso), ENT_QUOTES, 'UTF-8') ?></time>
                        <?php if ($processed !== null): ?>
                            <dl class="snap-meta">
                                <div><dt><?= htmlspecialchars(__('snap.size'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars(\App\Helpers\Format::bytes((int) $processed), ENT_QUOTES, 'UTF-8') ?></dd></div>
                            </dl>
                        <?php endif ?>
                    </header>
                    <?php if (!empty($paths)): ?>
                    <ul class="snap-paths">
                        <?php foreach ($paths as $p): ?>
                            <li><code><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></code></li>
                        <?php endforeach ?>
                    </ul>
                    <?php endif ?>
                </div>
                <footer class="snap-card-actions">
                    <a href="/browse?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>" class="btn-browse"><?= htmlspecialchars(__('snap.browse'), ENT_QUOTES, 'UTF-8') ?></a>
                </footer>
            </article>
        <?php endforeach ?>
        </div>
        <p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('dash.view_all'), ENT_QUOTES, 'UTF-8') ?></a></p>
    </div>
<?php elseif ($repo !== null && empty($latestSnapshots)): ?>
    <p><?= htmlspecialchars(__('snap.no_snaps'), ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif ($repo === null && $repoCount > 0): ?>
    <p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>
