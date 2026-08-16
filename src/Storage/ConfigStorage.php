<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Core\App;
use Symfony\Component\Yaml\Yaml;

class ConfigStorage
{
    private string $configDir;

    public function __construct(?string $configDir = null)
    {
        $this->configDir = $configDir ?? dirname(__DIR__, 2) . '/data/cfg';
    }

    /**
     * Загружает пользователей из users.php (приоритет) и users.yaml (дополнение).
     * При совпадении логина побеждает users.php.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $users = $this->loadPhpUsers();

        foreach ($this->loadYamlUsers() as $username => $data) {
            if (!isset($users[$username])) {
                $users[$username] = $data;
            }
        }

        return $users;
    }

    /**
     * Пользователи из users.php (только PHP-источник).
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadPhpUsers(): array
    {
        return $this->loadPhpFile('users.php');
    }

    /**
     * @return array{guest_user: ?string, debug: int, tmp_dir: string, log_dir: string, timezone: string, repo_base_dir?: string, backup_paths_roots?: array<int, string>, repo_paths_roots?: array<int, string>}
     */
    public function loadSettings(): array
    {
        return $this->loadPhpFile('settings.php');
    }

    /**
     * Путь к users.yaml (рядом с repositories.yaml в data/data/).
     */
    public function usersYamlPath(): string
    {
        return dirname($this->configDir) . '/data/users.yaml';
    }

    /**
     * Источник пользователя: 'php' (приоритетнее) | 'yaml' | null.
     */
    public function userSource(string $username): ?string
    {
        if (array_key_exists($username, $this->loadPhpUsers())) {
            return 'php';
        }
        if (array_key_exists($username, $this->loadYamlUsers())) {
            return 'yaml';
        }
        return null;
    }

    /**
     * Загружает users.yaml из data/data/users.yaml (рядом с repositories.yaml).
     * Отсутствующий или невалидный файл → пустой массив.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadYamlUsers(): array
    {
        $path = $this->usersYamlPath();

        if (!file_exists($path)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            App::log('Failed to parse users.yaml: ' . $e->getMessage(), 0);
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $users = [];
        foreach ($data as $username => $userData) {
            if (is_string($username) && is_array($userData)) {
                $users[$username] = $userData;
            }
        }

        return $users;
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
