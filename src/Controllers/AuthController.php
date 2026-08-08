<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class AuthController
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

        echo App::response()->render('login.php', [
            'error' => $flash,
            'csrfToken' => $csrfToken,
        ]);
    }

    public function login(): void
    {
        error_log('[LOGIN] START');

        $request = new Request();
        $auth = App::auth();
        $security = App::security();

        error_log('[LOGIN] POST data: ' . json_encode($request->allPost()));

        $token = $request->post('_csrf_token', '');
        error_log('[LOGIN] CSRF token from form: ' . ($token !== '' ? 'present' : 'EMPTY'));

        if (!$security->validateCsrf($token)) {
            error_log('[LOGIN] CSRF validation FAILED');
            App::session()->flash('error', 'Invalid security token. Please try again.');
            App::response()->redirect('/login');
            return;
        }
        error_log('[LOGIN] CSRF OK');

        $username = $request->post('username', '');
        $password = $request->post('password', '');

        error_log('[LOGIN] username=' . ($username !== '' ? $username : 'EMPTY') . ' password=' . ($password !== '' ? '***' : 'EMPTY'));

        if ($username === '' || $password === '') {
            error_log('[LOGIN] Empty credentials, redirecting');
            App::session()->flash('error', 'Username and password are required.');
            App::response()->redirect('/login');
            return;
        }

        error_log('[LOGIN] Calling auth->login()...');

        if ($auth->login($username, $password)) {
            error_log('[LOGIN] SUCCESS');
            App::response()->redirect('/repositories');
            return;
        }

        error_log('[LOGIN] FAILED - password_verify returned false');
        App::session()->flash('error', 'Invalid username or password.');
        App::response()->redirect('/login');
    }

    public function logout(): void
    {
        App::auth()->logout();
        App::response()->redirect('/login');
    }
}
