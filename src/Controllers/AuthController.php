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
        App::log('POST /login — START', 1);

        $request = new Request();
        $auth = App::auth();
        $security = App::security();

        App::log('POST data: ' . json_encode($request->allPost()), 1);

        $token = $request->post('_csrf_token', '');
        App::log('CSRF token from form: ' . ($token !== '' ? 'present' : 'EMPTY'), 1);

        if (!$security->validateCsrf($token)) {
            App::log('CSRF validation FAILED', 1);
            App::session()->flash('error', 'Invalid security token. Please try again.');
            App::response()->redirect('/login');
            return;
        }
        App::log('CSRF OK', 1);

        $username = $request->post('username', '');
        $password = $request->post('password', '');

        App::log('username=' . ($username !== '' ? $username : 'EMPTY') . ' password=' . ($password !== '' ? '***' : 'EMPTY'), 1);

        if ($username === '' || $password === '') {
            App::log('Empty credentials, redirecting', 1);
            App::session()->flash('error', 'Username and password are required.');
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
        App::session()->flash('error', 'Invalid username or password.');
        App::response()->redirect('/login');
    }

    public function logout(): void
    {
        App::auth()->logout();
        App::response()->redirect('/login');
    }
}
