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
 * Управление YAML-пользователями (только для can_manage_users).
 */
class UserController
{
    private UserStorage $users;

    public function __construct(?UserStorage $users = null)
    {
        $this->users = $users ?? new UserStorage(App::configStorage());
    }

    /**
     * GET /users — список php (read-only) и yaml пользователей.
     */
    public function list(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $all = $this->users->listAll();

        echo App::response()->render('users/list.php', [
            'phpUsers' => $all['php'],
            'yamlUsers' => $all['yaml'],
            'currentUser' => $user,
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * GET /users/add — форма добавления.
     */
    public function addForm(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        echo App::response()->render('users/form.php', [
            'mode' => 'add',
            'editingUser' => null,
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /users/add
     */
    public function add(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $security = App::security();

        if (!$security->validateCsrf($request->post('_csrf_token', ''))) {
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/users');
            return;
        }

        $username = trim((string) $request->post('username', ''));

        try {
            $this->users->create($username, $this->userDataFromPost($request, false));
        } catch (\Throwable $e) {
            App::session()->flash('error', $e->getMessage());
            App::response()->redirect('/users/add');
            return;
        }

        App::session()->flash('success', __('users.created'));
        App::response()->redirect('/users');
    }

    /**
     * GET /users/edit
     */
    public function editForm(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $username = (string) $request->get('username', '');

        $yamlUsers = $this->users->listAll()['yaml'];
        if (!isset($yamlUsers[$username])) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        echo App::response()->render('users/form.php', [
            'mode' => 'edit',
            'editingUser' => ['username' => $username, 'data' => $yamlUsers[$username]],
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /users/edit
     */
    public function edit(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $security = App::security();

        if (!$security->validateCsrf($request->post('_csrf_token', ''))) {
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/users');
            return;
        }

        $username = (string) $request->post('username', '');

        try {
            $this->users->update($username, $this->userDataFromPost($request, true));
        } catch (\Throwable $e) {
            App::session()->flash('error', $e->getMessage());
            App::response()->redirect('/users');
            return;
        }

        App::session()->flash('success', __('users.updated'));
        App::response()->redirect('/users');
    }

    /**
     * POST /users/delete
     */
    public function delete(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canManageUsers()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $security = App::security();

        if (!$security->validateCsrf($request->post('_csrf_token', ''))) {
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/users');
            return;
        }

        $username = (string) $request->post('username', '');

        if ($username === $user) {
            App::session()->flash('error', __('users.cannot_delete_self'));
            App::response()->redirect('/users');
            return;
        }

        try {
            $this->users->delete($username);
        } catch (\Throwable $e) {
            App::session()->flash('error', $e->getMessage());
            App::response()->redirect('/users');
            return;
        }

        App::session()->flash('success', __('users.deleted'));
        App::response()->redirect('/users');
    }

    /**
     * @return array<string, mixed>
     */
    private function userDataFromPost(Request $request, bool $isEdit): array
    {
        $data = [
            'api_tokens' => $this->apiTokensFromPost($request),
            'can_init' => $request->post('can_init', '0') === '1',
            'can_delete' => $request->post('can_delete', '0') === '1',
            'can_manage_users' => $request->post('can_manage_users', '0') === '1',
            'can_manage_processes' => $request->post('can_manage_processes', '0') === '1',
            'repos' => $this->reposFromPost($request),
        ];

        $password = (string) $request->post('password', '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $passwordVar = trim((string) $request->post('password_var', ''));
        $data['password_var'] = $passwordVar !== '' ? $passwordVar : null;

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function apiTokensFromPost(Request $request): array
    {
        $raw = (string) $request->post('api_tokens', '');
        $tokens = array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            function (string $t): bool { return $t !== ''; }
        ));

        return $tokens;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function reposFromPost(Request $request): array
    {
        $input = $request->post('repos', []);
        if (!is_array($input)) {
            $input = [];
        }

        $repos = [];
        foreach (['public', 'private', 'session'] as $cat) {
            $catInput = $input[$cat] ?? [];
            if (!is_array($catInput)) {
                $catInput = [];
            }
            $repos[$cat] = [
                'use' => ($catInput['use'] ?? '') === '1',
                'use_read' => ($catInput['use_read'] ?? '') === '1',
                'use_write' => ($catInput['use_write'] ?? '') === '1',
                'edit' => ($catInput['edit'] ?? '') === '1',
            ];
        }

        return $repos;
    }
}
