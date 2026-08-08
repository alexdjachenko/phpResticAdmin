<?php
// Фильтруем безобидные предупреждения restic о кеше
$filteredError = '';
if ($error !== '') {
    $lines = explode("\n", $error);
    $filtered = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '' || str_contains($t, 'unable to open cache') || str_contains($t, '$XDG_CACHE_HOME') || str_contains($t, '$HOME are defined')) {
            continue;
        }
        $filtered[] = $line;
    }
    $filteredError = implode("\n", $filtered);
}
?>
<h2><?= htmlspecialchars(__('repo.backup'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>

<p><a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('repo.detail_back'), ENT_QUOTES, 'UTF-8') ?></a></p>

<?php if ($ok): ?>
    <div class="flash"><?= htmlspecialchars(__('flash.backup_ok'), ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>
    <div class="flash flash-error"><?= htmlspecialchars(__('flash.backup_failed'), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

<pre class="backup-output"><?= htmlspecialchars($output, ENT_QUOTES, 'UTF-8') ?></pre>

<?php if ($filteredError !== ''): ?>
    <h3>Errors</h3>
    <pre class="backup-output backup-error"><?= htmlspecialchars($filteredError, ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif ?>

<p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('repo.view_all_snaps'), ENT_QUOTES, 'UTF-8') ?></a></p>
