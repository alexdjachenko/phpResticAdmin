<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Core\App;

class ConfigStorage
{
    private string $configDir;

    public function __construct(?string $configDir = null)
    {
        $this->configDir = $configDir ?? dirname(__DIR__, 2) . '/data/cfg';
    }

    /**
     * @return array<string, array{password: string}>
     */
    public function loadUsers(): array
    {
        return $this->loadPhpFile('users.php');
    }

    /**
     * @return array{guest_user: ?string, debug: int, tmp_dir: string, log_dir: string, timezone: string}
     */
    public function loadSettings(): array
    {
        return $this->loadPhpFile('settings.php');
    }

    private function loadPhpFile(string $filename): array
    {
        $path = $this->configDir . '/' . $filename;

        if (!file_exists($path)) {
            App::log('Config file not found: ' . $path, 2);
            return [];
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $data = require $path;

        App::log('Loaded config: ' . $path . ' keys=' . (is_array($data) ? count($data) : 'NOT_ARRAY'), 2);

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }
}
