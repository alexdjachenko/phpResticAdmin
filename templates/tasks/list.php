<h2><?= htmlspecialchars(__('tasks.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (empty($tasks)): ?>
    <p><?= htmlspecialchars(__('tasks.none'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <table class="snapshot-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= htmlspecialchars(__('tasks.state'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('tasks.name'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('tasks.owner'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('tasks.command'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= (int) $task['id'] ?></td>
                    <td><?= htmlspecialchars(__('tasks.state_' . $task['state']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (!empty($task['valid']) && $task['label'] !== ''): ?>
                            <a href="/tasks/view?label=<?= htmlspecialchars(urlencode($task['label']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php if (!empty($task['repoName'])): ?>
                                <span class="muted">— <?= htmlspecialchars($task['repoName'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif ?>
                        <?php else: ?>
                            <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif ?>
                    </td>
                    <td><?= htmlspecialchars($task['owner'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($task['command'], ENT_QUOTES, 'UTF-8') ?></code></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
