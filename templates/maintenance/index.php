<div class="breadcrumb">
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2><?= htmlspecialchars(__('maint.title'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.check_connection'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.check_connection_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/connection">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.check'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.check_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/check">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.stats'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.stats_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/stats">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<?php if (\App\Core\App::auth()->canInit()): ?>
<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.init'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.init_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="maintenance-warn"><?= htmlspecialchars(__('maint.init_warn'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/init">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.init_button'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>
<?php endif ?>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.prune'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.prune_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/prune">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.rebuild_index'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.rebuild_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/rebuild-index">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.unlock'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.unlock_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="maintenance-warn"><?= htmlspecialchars(__('maint.unlock_warn'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/unlock">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('maint.forget'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(__('maint.forget_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/maintenance/forget">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="repo_id" value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="retention-fields">
            <div class="form-group">
                <label><?= htmlspecialchars(__('maint.keep_daily'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" name="keep_daily" value="0" min="0">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(__('maint.keep_weekly'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" name="keep_weekly" value="0" min="0">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(__('maint.keep_monthly'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" name="keep_monthly" value="0" min="0">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(__('maint.keep_yearly'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" name="keep_yearly" value="0" min="0">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(__('maint.keep_last'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" name="keep_last" value="0" min="0">
            </div>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="prune" value="1">
                <?= htmlspecialchars(__('maint.prune_after'), ENT_QUOTES, 'UTF-8') ?>
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="dry_run" value="1" checked>
                <?= htmlspecialchars(__('maint.dry_run'), ENT_QUOTES, 'UTF-8') ?>
            </label>
        </div>
        <button type="submit" class="btn-primary"><?= htmlspecialchars(__('maint.run'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>
