<?php
return [
    'guest_user' => 'guest',
    'debug' => 0,
    'tmp_dir' => '/tmp/phpresticadmin',
    'log_dir' => __DIR__ . '/../logs',
    'timezone' => 'UTC',
    'repo_base_dir' => '/backups',
    'backup_paths_roots' => ['/sources'],
    'repo_paths_roots' => ['/backups'],
];
