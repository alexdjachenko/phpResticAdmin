<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load helpers to guarantee __() is available regardless of composer autoload state
require_once __DIR__ . '/../src/Helpers/functions.php';

\App\Core\App::boot();
\App\Core\App::run();
