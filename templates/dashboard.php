<h2><?= htmlspecialchars(__('dash.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if ($repoCount === 0): ?>
    <p><?= __('dash.no_repos') ?></p>
<?php else: ?>
    <p><?= htmlspecialchars(__('dash.select_repo'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>
