<?php

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

    /**
     * Возвращает имя текущего пользователя или guest_user, либо null.
     */
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

    public function canInit(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['init'] ?? false;
    }

    public function canDelete(string $category): bool
    {
        $config = $this->getReposConfig();
        return $config[$category]['delete'] ?? false;
    }

    public function canMove(string $fromCategory, string $toCategory): bool
    {
        return $this->canEdit($fromCategory) && $this->canEdit($toCategory);
    }

    /**
     * @return array<string, array{use: bool, edit: bool, init: bool, delete: bool}>
     */
    public function getReposConfig(): array
    {
        $user = $this->user();
        $users = $this->getUsers();

        $defaultFull = [
            'public'  => ['use' => true, 'edit' => true, 'init' => true, 'delete' => true],
            'private' => ['use' => true, 'edit' => true, 'init' => true, 'delete' => true],
            'session' => ['use' => true, 'edit' => true, 'init' => true, 'delete' => true],
        ];

        $defaultGuest = [
            'public'  => ['use' => true,  'edit' => false, 'init' => false, 'delete' => false],
            'private' => ['use' => false, 'edit' => false, 'init' => false, 'delete' => false],
            'session' => ['use' => false, 'edit' => false, 'init' => false, 'delete' => false],
        ];

        if ($user === null) {
            return $defaultGuest;
        }

        $userData = $users[$user] ?? null;

        if ($userData === null) {
            return $this->isGuest() ? $defaultGuest : $defaultFull;
        }

        $repos = $userData['repos'] ?? null;

        if ($repos === null) {
            return $this->isGuest() ? $defaultGuest : $defaultFull;
        }

        $result = [];
        foreach (['public', 'private', 'session'] as $category) {
            $cat = $repos[$category] ?? ['use' => false, 'edit' => false, 'init' => false, 'delete' => false];
            $edit = $cat['edit'] ?? false;
            $use = $edit || ($cat['use'] ?? false);
            $init = $cat['init'] ?? false;
            $delete = $cat['delete'] ?? false;
            $result[$category] = ['use' => $use, 'edit' => $edit, 'init' => $init, 'delete' => $delete];
        }

        return $result;
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
