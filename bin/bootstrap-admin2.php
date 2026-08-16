<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Storage\UserBootstrap;

$usersFile = __DIR__ . '/../data/data/users.yaml';

$password = (new UserBootstrap())->ensureAdmin2($usersFile);

if ($password !== null) {
    echo "Created initial admin2 user. Login: admin2, Password: {$password}\n";
}
