<?php

namespace App\Storage;

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
     * @return array{guest_user: ?string, tmp_dir: string, log_dir: string, timezone: string}
     */
    public function loadSettings(): array
    {
        return $this->loadPhpFile('settings.php');
    }

    private function loadPhpFile(string $filename): array
    {
        $path = $this->configDir . '/' . $filename;

        if (!file_exists($path)) {
            return [];
        }

        $data = require $path;

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }
}
