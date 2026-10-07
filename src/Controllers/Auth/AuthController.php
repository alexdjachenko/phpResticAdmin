<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Core\App;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        $auth = App::auth();

        if ($auth->isLoggedIn()) {
            App::response()->redirect('/repositories');
            return;
        }

        $flash = App::session()->flash('error');
        $csrfToken = App::security()->csrfToken();

        $this->render('login.php', [
            'error' => $flash,
            'csrfToken' => $csrfToken,
        ]);
    }

    public function login(): void
    {
        App::log('POST /login — START', 1);

        $request = $this->request();
        $auth = App::auth();

        App::log('POST data: ' . json_encode($request->allPost()), 1);

        $token = (string) $request->post('_csrf_token', '');
        App::log('CSRF token from form: ' . ($token !== '' ? 'present' : 'EMPTY'), 1);

        if (!$this->requireCsrf(false, '/login')) {
            App::log('CSRF validation FAILED', 1);
            return;
        }
        App::log('CSRF OK', 1);

        $username = (string) $request->post('username', '');
        $password = (string) $request->post('password', '');

        App::log('username=' . ($username !== '' ? $username : 'EMPTY') . ' password=' . ($password !== '' ? '***' : 'EMPTY'), 1);

        if ($username === '' || $password === '') {
            App::log('Empty credentials, redirecting', 1);
            App::session()->flash('error', __('auth.error_empty'));
            App::response()->redirect('/login');
            return;
        }

        App::log('Calling auth->login()...', 1);

        if ($auth->login($username, $password)) {
            App::log('LOGIN SUCCESS', 0);
            App::response()->redirect('/repositories');
            return;
        }

        App::log('LOGIN FAILED — password_verify returned false', 1);
        App::session()->flash('error', __('auth.error_invalid'));
        App::response()->redirect('/login');
    }

    public function logout(): void
    {
        App::auth()->logout();
        App::response()->redirect('/login');
    }
}
