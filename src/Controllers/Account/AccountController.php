<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use App\Core\App;
use App\Storage\UserStorage;

/**
 * Self-service смена пароля (только для YAML-пользователей).
 */
class AccountController extends BaseController
{
    private UserStorage $users;

    public function __construct(?UserStorage $users = null)
    {
        $this->users = $users ?? new UserStorage(App::configStorage());
    }

    /**
     * GET /account/password — форма смены пароля.
     */
    public function passwordForm(): void
    {
        $user = $this->requireYamlUser();
        if ($user === null) {
            return;
        }

        $this->render('account/password.php', [
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /account/password
     */
    public function changePassword(): void
    {
        $user = $this->requireYamlUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/account/password')) {
            return;
        }

        $request = $this->request();
        $currentPassword = (string) $request->post('current_password', '');
        $newPassword = (string) $request->post('new_password', '');
        $confirmPassword = (string) $request->post('confirm_password', '');

        if ($currentPassword === '' || $newPassword === '' || $newPassword !== $confirmPassword) {
            App::session()->flash('error', __('account.password_mismatch'));
            App::response()->redirect('/account/password');
            return;
        }

        $hash = App::auth()->resolvePasswordHash($user);
        if ($hash === null || !password_verify($currentPassword, $hash)) {
            App::session()->flash('error', __('account.current_password_invalid'));
            App::response()->redirect('/account/password');
            return;
        }

        $this->users->updatePassword($user, password_hash($newPassword, PASSWORD_DEFAULT));

        App::session()->flash('success', __('account.password_changed'));
        App::response()->redirect('/account/password');
    }

    /**
     * Требует YAML-пользователя.
     *
     * @return string|null
     */
    private function requireYamlUser(): ?string
    {
        $user = $this->requireUser();
        if ($user === null) {
            return null;
        }

        if (!App::auth()->isYamlUser()) {
            App::response()->error(403, __('error.forbidden'));
            return null;
        }

        return $user;
    }
}
