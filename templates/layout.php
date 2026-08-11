<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Helpers\Lang::getLocale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('app.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-left">
            <h1><a href="/"><?= htmlspecialchars(__('app.title'), ENT_QUOTES, 'UTF-8') ?></a></h1>
            <span class="nav-links">
                <a href="/"><?= htmlspecialchars(__('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/repositories"><?= htmlspecialchars(__('nav.repositories'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if (!empty($currentRepoId ?? null)): ?>
                <a href="/snapshots"><?= htmlspecialchars(__('nav.snapshots'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if (!empty($currentRepoCanUseWrite ?? false)): ?>
                <a href="/maintenance?repo=<?= htmlspecialchars(urlencode($currentRepoId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('nav.maintenance'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/keys?repo=<?= htmlspecialchars(urlencode($currentRepoId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('nav.keys'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif ?>
                <?php endif ?>
            </span>
        </div>
        <nav>
            <?php if (!empty($repositories ?? [])): ?>
            <form method="post" action="/repositories/select" class="repo-selector">
                <select name="repo_id" onchange="this.form.submit()">
                    <option value="">-- repos --</option>
                    <?php foreach ($repositories as $repo): ?>
                        <?php
                        $cat = $repo['category'] ?? 'public';
                        $selected = (($repo['id'] ?? '') === ($currentRepoId ?? '')) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($repo['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                            <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </form>
            <?php endif ?>

            <span class="lang-switcher">
                <?php foreach (\App\Helpers\Lang::available() as $langCode): ?>
                    <form method="post" action="/language" style="display:inline">
                        <input type="hidden" name="lang" value="<?= htmlspecialchars($langCode, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="lang-btn <?= \App\Helpers\Lang::getLocale() === $langCode ? 'active' : '' ?>">
                            <?= htmlspecialchars(strtoupper($langCode), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </form>
                <?php endforeach ?>
            </span>
            <?php if (isset($isLoggedIn) && $isLoggedIn && isset($username)): ?>
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?> | <a href="/logout"><?= htmlspecialchars(__('nav.logout'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a href="/login"><?= htmlspecialchars(__('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif ?>
            <?php if (!empty($debug)): ?>
                <span class="debug-badge">DEBUG</span>
            <?php endif ?>
        </nav>
    </header>

    <?php if (!empty($flash)): ?>
        <div class="flash"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <main><?= $content ?? '' ?></main>

    <footer class="app-footer">
        © 2026 Alex Djachenko (Алексей Дьяченко)
        &middot; <strong><a href="https://github.com/alexdjachenko/phpResticAdmin" target="_blank" rel="noopener">phpResticAdmin</a></strong> v<?= htmlspecialchars($appVersion ?? 'dev', ENT_QUOTES, 'UTF-8') ?>
        &middot; restic <?= htmlspecialchars($resticVersion ?? '', ENT_QUOTES, 'UTF-8') ?>
        &middot; <a href="https://www.apache.org/licenses/LICENSE-2.0" target="_blank" rel="noopener">Apache 2.0</a>
    </footer>
</body>
</html>
