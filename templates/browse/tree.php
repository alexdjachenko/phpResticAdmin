<?php
$repoId = $repo['id'] ?? '';
?>
<div class="breadcrumbs">
    <?php $last = count($breadcrumbs) - 1; foreach ($breadcrumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?> <span class="breadcrumb-sep">&gt;</span> <?php endif ?>
        <?php if ($crumb['url'] !== null && $i < $last): ?>
            <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php else: ?>
            <span class="breadcrumb-current"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif ?>
    <?php endforeach ?>
</div>

<h2><?= htmlspecialchars(__('browse.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<p><a href="/snapshots?repo=<?= htmlspecialchars(urlencode($repoId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('browse.back'), ENT_QUOTES, 'UTF-8') ?></a></p>

<?php if (empty($dirs) && empty($files)): ?>
    <p><?= htmlspecialchars(__('browse.empty_dir'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <table class="browse-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('browse.name'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('browse.size'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(__('browse.modified'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dirs as $entry): ?>
                <tr class="browse-dir">
                    <td>
                        <a href="/browse?repo=<?= htmlspecialchars(urlencode($repoId), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>&path=<?= htmlspecialchars(urlencode($entry['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>/
                        </a>
                    </td>
                    <td>-</td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::date($entry['mtime'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach ?>
            <?php foreach ($files as $entry): ?>
                <tr class="browse-file">
                    <td>
                        <?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        <a href="/download?repo=<?= htmlspecialchars(urlencode($repoId), ENT_QUOTES, 'UTF-8') ?>&snapshot=<?= htmlspecialchars(urlencode($snapId), ENT_QUOTES, 'UTF-8') ?>&path=<?= htmlspecialchars(urlencode($entry['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="btn-download" title="<?= htmlspecialchars(__('export.download'), ENT_QUOTES, 'UTF-8') ?>">&darr;</a>
                    </td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::bytes((int) ($entry['size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\App\Helpers\Format::date($entry['mtime'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
