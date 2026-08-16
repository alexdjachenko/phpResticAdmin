<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use Symfony\Component\Yaml\Yaml;

/**
 * CRUD YAML-пользователей (data/data/users.yaml).
 *
 * Переиспользует паттерн записи YAML из RepositoryStorage. PHP-пользователи
 * (users.php) доступны только для чтения и защищены от изменения/удаления.
 */
class UserStorage
{
    private ConfigStorage $configStorage;

    public function __construct(ConfigStorage $configStorage)
    {
        $this->configStorage = $configStorage;
    }

    /**
     * @return array{php: array<string, array<string, mixed>>, yaml: array<string, array<string, mixed>>}
     */
    public function listAll(): array
    {
        return [
            'php' => $this->configStorage->loadPhpUsers(),
            'yaml' => $this->configStorage->loadYamlUsers(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(string $username, array $data): void
    {
        $this->validateUsername($username);

        if ($this->configStorage->userSource($username) !== null) {
            throw new \RuntimeException('User already exists: ' . $username);
        }

        $users = $this->configStorage->loadYamlUsers();
        $users[$username] = $data;
        $this->write($users);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $username, array $data): void
    {
        $this->validateUsername($username);

        if ($this->configStorage->userSource($username) !== 'yaml') {
            throw new \RuntimeException('Only YAML users can be updated: ' . $username);
        }

        $users = $this->configStorage->loadYamlUsers();
        $users[$username] = array_merge($users[$username] ?? [], $data);
        $this->write($users);
    }

    public function delete(string $username): void
    {
        if ($this->configStorage->userSource($username) !== 'yaml') {
            throw new \RuntimeException('Only YAML users can be deleted: ' . $username);
        }

        $users = $this->configStorage->loadYamlUsers();
        unset($users[$username]);
        $this->write($users);
    }

    public function updatePassword(string $username, string $hash): void
    {
        $this->update($username, ['password' => $hash]);
    }

    private function validateUsername(string $username): void
    {
        if ($username === '' || preg_match('/^[A-Za-z0-9._-]+$/', $username) !== 1) {
            throw new \InvalidArgumentException('Invalid username: ' . $username);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    private function write(array $users): void
    {
        $path = $this->configStorage->usersYamlPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, Yaml::dump($users, 4, 2));
    }
}
