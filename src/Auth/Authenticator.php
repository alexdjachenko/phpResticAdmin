<?php

namespace App\Auth;

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
            error_log('[AUTH] User not found: ' . $username);
            return false;
        }

        $hash = $users[$username]['password'];
        error_log('[AUTH] Checking password for ' . $username . ', hash prefix: ' . substr($hash, 0, 10) . '...');

        if (!password_verify($password, $hash)) {
            error_log('[AUTH] password_verify FAILED for ' . $username . ', need_rehash: ' . (password_needs_rehash($hash, PASSWORD_DEFAULT) ? 'yes' : 'no'));
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
