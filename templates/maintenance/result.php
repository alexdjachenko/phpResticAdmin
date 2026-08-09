<div class="breadcrumb">
    <a href="/maintenance?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back'), ENT_QUOTES, 'UTF-8') ?></a>
    &middot;
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2><?= htmlspecialchars(__('maint.result_title', ['{action}' => $action]), ENT_QUOTES, 'UTF-8') ?></h2>

<?php if (!empty($dryRun)): ?>
    <div class="result-info"><?= htmlspecialchars(__('maint.dry_run_note'), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<?php if ($result['ok']): ?>
    <div class="result-ok"><?= htmlspecialchars(__('maint.status_ok'), ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>
    <div class="result-error"><?= htmlspecialchars(__('maint.status_error'), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<?php if (!empty($result['output'])): ?>
    <h3><?= htmlspecialchars(__('maint.output'), ENT_QUOTES, 'UTF-8') ?></h3>
    <pre class="maintenance-output"><?= htmlspecialchars($result['output'], ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif ?>

<?php if (!empty($result['error'])): ?>
    <pre class="maintenance-output<?= $result['ok'] ? ' maintenance-stderr-info' : '' ?>"><?= htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif ?>
