<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpresticadmin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <h1><a href="/">phpresticadmin</a></h1>
        <nav>
            <?php if (isset($isLoggedIn) && $isLoggedIn && isset($username)): ?>
                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?> | <a href="/logout">Logout</a>
            <?php else: ?>
                <a href="/login">Login</a>
            <?php endif ?>
        </nav>
    </header>

    <?php if (!empty($flash)): ?>
        <div class="flash"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <main><?= $content ?? '' ?></main>
</body>
</html>
