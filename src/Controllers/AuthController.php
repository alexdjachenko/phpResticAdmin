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
        $request = new Request();
        $auth = App::auth();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::session()->flash('error', 'Invalid security token. Please try again.');
            App::response()->redirect('/login');
            return;
        }

        $username = $request->post('username', '');
        $password = $request->post('password', '');

        if ($username === '' || $password === '') {
            App::session()->flash('error', 'Username and password are required.');
            App::response()->redirect('/login');
            return;
        }

        if ($auth->login($username, $password)) {
            App::response()->redirect('/repositories');
            return;
        }

        App::session()->flash('error', 'Invalid username or password.');
        App::response()->redirect('/login');
    }

    public function logout(): void
    {
        App::auth()->logout();
        App::response()->redirect('/login');
    }
}
