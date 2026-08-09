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

        $hash = $users[$username]['password'];

        if (!password_verify($password, $hash)) {
            App::log('password_verify FAILED for ' . $username, 1);
            return false;
        }

        $this->session->set('auth_user', $username);
        return true;
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

    public function canUse(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['use'] ?? false;
    }

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

    public function canMove(string $fromCategory, string $toCategory): bool
    {
        return $this->canEdit($fromCategory) && $this->canEdit($toCategory);
    }

    /**
     * @return array<string, array{use: bool, edit: bool}>
     */
    public function getReposConfig(): array
    {
        $user = $this->user();
        $userData = $this->getUserData();

        $defaultFull = [
            'public'  => ['use' => true, 'edit' => true],
            'private' => ['use' => true, 'edit' => true],
            'session' => ['use' => true, 'edit' => true],
        ];

        $defaultGuest = [
            'public'  => ['use' => true,  'edit' => false],
            'private' => ['use' => false, 'edit' => false],
            'session' => ['use' => false, 'edit' => false],
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
            $cat = $repos[$category] ?? ['use' => false, 'edit' => false];
            $edit = $cat['edit'] ?? false;
            $use = $edit || ($cat['use'] ?? false);
            $result[$category] = ['use' => $use, 'edit' => $edit];
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
