<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Storage\UserStorage;

/**
 * Self-service смена пароля (только для YAML-пользователей).
 */
class AccountController
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
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->isYamlUser()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        echo App::response()->render('account/password.php', [
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /account/password
     */
    public function changePassword(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->isYamlUser()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $security = App::security();

        if (!$security->validateCsrf($request->post('_csrf_token', ''))) {
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/account/password');
            return;
        }

        $currentPassword = (string) $request->post('current_password', '');
        $newPassword = (string) $request->post('new_password', '');
        $confirmPassword = (string) $request->post('confirm_password', '');

        if ($currentPassword === '' || $newPassword === '' || $newPassword !== $confirmPassword) {
            App::session()->flash('error', __('account.password_mismatch'));
            App::response()->redirect('/account/password');
            return;
        }

        $hash = $auth->resolvePasswordHash($user);
        if ($hash === null || !password_verify($currentPassword, $hash)) {
            App::session()->flash('error', __('account.current_password_invalid'));
            App::response()->redirect('/account/password');
            return;
        }

        $this->users->updatePassword($user, password_hash($newPassword, PASSWORD_DEFAULT));

        App::session()->flash('success', __('account.password_changed'));
        App::response()->redirect('/account/password');
    }
}
