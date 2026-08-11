<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class KeyController
{
    /**
     * GET /keys — список ключей репозитория.
     */
    public function list(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $this->resolveRepoId($request);

        if ($repoId === null) {
            App::session()->flash('success', __('flash.select_repo'));
            App::response()->redirect('/repositories');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseRead($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $keys = App::keyService()->listKeys($repo);
        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('keys/list.php', [
            'repo' => $repo,
            'keys' => $keys,
            'csrfToken' => $csrfToken,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /keys/verify
     */
    public function verify(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        $password = $request->post('password', '');

        if ($repoId === '' || $password === '') {
            App::session()->flash('error', __('keys.verify_fail'));
            App::response()->redirect('/keys?repo=' . urlencode($repoId));
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseRead($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::keyService()->verifyKey($repo, $password);

        if ($result['ok']) {
            App::session()->flash('success', __('keys.verify_ok'));
        } else {
            App::session()->flash('error', __('keys.verify_fail') . ': ' . $result['error']);
        }

        App::response()->redirect('/keys?repo=' . urlencode($repoId));
    }

    /**
     * POST /keys/add
     */
    public function add(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        $newPassword = $request->post('new_password', '');

        if ($repoId === '' || $newPassword === '') {
            App::session()->flash('error', __('keys.add_error'));
            App::response()->redirect('/keys?repo=' . urlencode($repoId));
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::keyService()->addKey($repo, $newPassword);

        if ($result['ok']) {
            App::session()->flash('success', __('keys.added'));
        } else {
            App::session()->flash('error', __('keys.add_error') . ': ' . $result['error']);
        }

        App::response()->redirect('/keys?repo=' . urlencode($repoId));
    }

    /**
     * POST /keys/remove
     */
    public function remove(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        $keyId = $request->post('key_id', '');

        if ($repoId === '' || $keyId === '') {
            App::response()->redirect('/keys?repo=' . urlencode($repoId));
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::keyService()->removeKey($repo, $keyId);

        if ($result['ok']) {
            App::session()->flash('success', __('keys.removed'));
        } else {
            App::session()->flash('error', __('keys.add_error') . ': ' . $result['error']);
        }

        App::response()->redirect('/keys?repo=' . urlencode($repoId));
    }

    /**
     * POST /keys/passwd
     */
    public function passwd(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        $keyId = $request->post('key_id', '');
        $newPassword = $request->post('new_password', '');

        if ($repoId === '' || $keyId === '' || $newPassword === '') {
            App::session()->flash('error', __('keys.add_error'));
            App::response()->redirect('/keys?repo=' . urlencode($repoId));
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::keyService()->changePassword($repo, $keyId, $newPassword);

        if ($result['ok']) {
            App::session()->flash('success', __('keys.passwd_changed'));
        } else {
            App::session()->flash('error', __('keys.add_error') . ': ' . $result['error']);
        }

        App::response()->redirect('/keys?repo=' . urlencode($repoId));
    }

    private function resolveRepoId(Request $request): ?string
    {
        $repoId = $request->get('repo', '');
        if ($repoId !== '') {
            return $repoId;
        }
        $sessionRepoId = App::session()->get('current_repo');
        if ($sessionRepoId !== null) {
            return $sessionRepoId;
        }
        return null;
    }
}
