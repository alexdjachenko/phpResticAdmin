<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Auth;

use App\Core\App;
use App\Core\Session;
use App\Storage\ConfigStorage;

class Authenticator
{
    private ConfigStorage $configStorage;
    private Session $session;
    private ?array $users = null;
    private ?array $settings = null;

    public function __construct(ConfigStorage $configStorage, Session $session)
    {
        $this->configStorage = $configStorage;
        $this->session = $session;
    }

    public function resolve(): ?string
    {
        $authUser = $this->session->get('auth_user');
        if ($authUser !== null) {
            return $authUser;
        }

        $settings = $this->getSettings();
        $guestUser = $settings['guest_user'] ?? null;

        if ($guestUser !== null) {
            return $guestUser;
        }

        return null;
    }

    public function login(string $username, string $password): bool
    {
        $users = $this->getUsers();

        if (!isset($users[$username])) {
            App::log('User not found: ' . $username, 1);
            return false;
        }

        $hash = $this->resolvePasswordHash($username);

        if ($hash === null || $hash === '' || !password_verify($password, $hash)) {
            App::log('password_verify FAILED for ' . $username, 1);
            return false;
        }

        $this->session->set('auth_user', $username);
        return true;
    }

    /**
     * Разрешает bcrypt-хеш пароля пользователя.
     *
     * Порядок: Docker-secret /run/secrets/<password_var> → getenv(<password_var>)
     * → поле password учётки. Механизм общий для всех пользователей (php и yaml).
     */
    public function resolvePasswordHash(string $username): ?string
    {
        $users = $this->getUsers();
        $userData = $users[$username] ?? null;

        if ($userData === null) {
            return null;
        }

        $passwordVar = $userData['password_var'] ?? null;
        if (is_string($passwordVar) && $passwordVar !== '') {
            $secretFile = '/run/secrets/' . $passwordVar;
            if (is_file($secretFile)) {
                $content = @file_get_contents($secretFile);
                $hash = $content !== false ? trim($content) : '';
                if ($hash !== '') {
                    return $hash;
                }
            }

            $envValue = getenv($passwordVar);
            if (is_string($envValue) && $envValue !== '') {
                return $envValue;
            }
        }

        $password = $userData['password'] ?? null;

        return is_string($password) && $password !== '' ? $password : null;
    }

    public function logout(): void
    {
        $this->session->remove('auth_user');
    }

    public function user(): ?string
    {
        return $this->resolve();
    }

    public function isGuest(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return true;
        }
        $settings = $this->getSettings();
        $guestUser = $settings['guest_user'] ?? null;
        return $guestUser !== null && $user === $guestUser;
    }

    public function isLoggedIn(): bool
    {
        return $this->session->get('auth_user') !== null;
    }

    /**
     * Является ли текущий пользователь YAML-пользователем (а не из users.php).
     */
    public function isYamlUser(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        return $this->configStorage->userSource($user) === 'yaml';
    }

    /**
     * Видимость репозитория в интерфейсе, базовые мета-данные.
     * Старое право 'use' регрессирует в этот вариант.
     */
    public function canUse(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['use'] ?? false;
    }

    /**
     * Право читать контент репозитория: навигация по снепшотам (browse),
     * скачивание файлов и архивов (download, export), список ключей.
     */
    public function canUseRead(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['use_read'] ?? false;
    }

    /**
     * Право вносить изменения в restic-репозиторий (не в запись о нём):
     * backup, tag, maintenance, keys add/remove/passwd, copy (как цель).
     */
    public function canUseWrite(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['use_write'] ?? false;
    }

    /**
     * Право изменять запись о репозитории (CRUD): имя, путь, пароль, удалить.
     * Старое право 'edit' регрессирует в этот вариант.
     */
    public function canEdit(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['edit'] ?? false;
    }

    /**
     * Глобальное право инициализировать новые restic-репозитории.
     */
    public function canInit(): bool
    {
        $userData = $this->getUserData();
        return $userData['can_init'] ?? $this->isLoggedIn();
    }

    /**
     * Глобальное право удалять репозитории.
     */
    public function canDelete(): bool
    {
        $userData = $this->getUserData();
        return $userData['can_delete'] ?? $this->isLoggedIn();
    }

    /**
     * Глобальное право управлять YAML-пользователями (/users).
     */
    public function canManageUsers(): bool
    {
        $userData = $this->getUserData();
        return $userData['can_manage_users'] ?? false;
    }

    /**
     * Глобальное право видеть фоновые задачи всех пользователей.
     */
    public function canManageProcesses(): bool
    {
        $userData = $this->getUserData();
        return $userData['can_manage_processes'] ?? false;
    }

    public function canMove(string $fromCategory, string $toCategory): bool
    {
        return $this->canUseRead($fromCategory) && $this->canUseWrite($toCategory);
    }

    /**
     * @return array<string, array{use: bool, use_read: bool, use_write: bool, edit: bool}>
     */
    public function getReposConfig(): array
    {
        $user = $this->user();
        $userData = $this->getUserData();

        $defaultFull = [
            'public'  => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
            'private' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
            'session' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
        ];

        $defaultGuest = [
            'public'  => ['use' => true, 'use_read' => true,  'use_write' => false, 'edit' => false],
            'private' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
            'session' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
        ];

        if ($user === null) {
            return $defaultGuest;
        }

        if ($userData === null) {
            return $this->isGuest() ? $defaultGuest : $defaultFull;
        }

        $repos = $userData['repos'] ?? null;

        if ($repos === null) {
            return $this->isGuest() ? $defaultGuest : $defaultFull;
        }

        $result = [];
        foreach (['public', 'private', 'session'] as $category) {
            $cat = $repos[$category] ?? ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false];

            // use (видимость) — старый ключ 'use'
            $use = $cat['use'] ?? false;

            // use_read (чтение контента) — новый ключ, без fallback'ов
            $useRead = $cat['use_read'] ?? false;

            // use_write (запись в restic) — новый ключ, без fallback'ов
            $useWrite = $cat['use_write'] ?? false;

            // edit (CRUD записи) — старый ключ 'edit'
            $edit = $cat['edit'] ?? false;

            // use_write подразумевает use_read
            if ($useWrite) {
                $useRead = true;
            }

            $result[$category] = [
                'use'       => $use,
                'use_read'  => $useRead,
                'use_write' => $useWrite,
                'edit'      => $edit,
            ];
        }

        return $result;
    }

    /**
     * @return array{password: ?string, can_init: bool, can_delete: bool, repos?: array}|null
     */
    private function getUserData(): ?array
    {
        $user = $this->user();
        if ($user === null) {
            return null;
        }
        $users = $this->getUsers();
        return $users[$user] ?? null;
    }

    private function getUsers(): array
    {
        if ($this->users === null) {
            $this->users = $this->configStorage->loadUsers();
        }
        return $this->users;
    }

    private function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = $this->configStorage->loadSettings();
        }
        return $this->settings;
    }
}
