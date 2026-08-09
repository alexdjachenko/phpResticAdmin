<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load helpers to guarantee __() is available regardless of composer autoload state
require_once __DIR__ . '/../src/Helpers/functions.php';

\App\Core\App::boot();
\App\Core\App::run();
