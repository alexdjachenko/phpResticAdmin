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
    'tsp_binary' => 'tsp',
    'tsp_slots' => 1,
    'snapshot_cache_ttl' => 600,
    'snapshot_stats_cache_ttl' => 31536000,
    'task_poll_interval' => 3000,
    'cache_driver' => 'session',
    ];
