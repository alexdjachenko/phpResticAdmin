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
                <a href="/repositories"><?= htmlspecialchars(__('nav.repositories'), ENT_QUOTES, 'UTF-8') ?></a>
            </span>
        </div>
        <nav>
            <span class="lang-switcher">
                <?php foreach (\App\Helpers\Lang::available() as $langCode): ?>
                    <form method="post" action="/language" style="display:inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
</body>
</html>
