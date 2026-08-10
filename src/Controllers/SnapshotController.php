<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class SnapshotController
{
    /**
     * GET /snapshots — список снепшотов.
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
            echo App::response()->render('snapshots/list.php', [
                'snapshots' => [],
                'repo' => null,
                'isLoggedIn' => $auth->isLoggedIn(),
                'username' => $user,
            ]);
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

        $snapshots = App::snapshotService()->listSnapshots($repo);
        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('snapshots/list.php', [
            'snapshots' => $snapshots,
            'repo' => $repo,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'csrfToken' => $csrfToken,
        ]);
    }

    /**
     * GET /snapshots/detail — страница снепшота со сводкой и кнопкой «Stats».
     */
    public function detail(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $request->get('repo', '');
        $snapId = $request->get('snapshot', '');

        if ($repoId === '' || $snapId === '') {
            App::response()->redirect('/snapshots');
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

        $snap = App::snapshotService()->getSnapshot($repo, $snapId);
        if ($snap === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $csrfToken = App::security()->csrfToken();

        $destRepos = [];
        foreach ($repositories as $r) {
            $cat = $r['category'] ?? 'public';
            if (($r['id'] ?? '') !== $repoId && $auth->canUseWrite($cat)) {
                $destRepos[] = ['id' => $r['id'], 'name' => $r['name']];
            }
        }

        echo App::response()->render('snapshots/detail.php', [
            'repo' => $repo,
            'snap' => $snap,
            'csrfToken' => $csrfToken,
            'destRepos' => $destRepos,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /snapshots/stats — загрузить полную статистику (AJAX).
     */
    public function stats(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => __('flash.csrf_error'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $repoId = $request->post('repo_id', '');
        $snapId = $request->post('snap_id', '');

        if ($repoId === '' || $snapId === '') {
            App::response()->json(['ok' => false, 'error' => 'Missing parameters', '_csrf_token' => App::security()->csrfToken()], 400);
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
            App::response()->json(['ok' => false, 'error' => __('flash.not_found'), '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseRead($category)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $stats = App::snapshotService()->getStats($repo, $snapId);

        App::response()->json([
            'ok' => $stats !== null,
            'stats' => $stats,
            '_csrf_token' => App::security()->csrfToken(),
        ]);
    }

    /**
     * POST /snapshots/tag — тегирование (AJAX).
     */
    public function tag(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => __('flash.csrf_error'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $repoId = $request->post('repo_id', '');
        $snapId = $request->post('snap_id', '');
        $tag = $request->post('tag', '');
        $action = $request->post('action', 'add');

        if ($repoId === '' || $snapId === '' || $tag === '') {
            App::response()->json(['ok' => false, 'error' => 'Missing parameters', '_csrf_token' => App::security()->csrfToken()], 400);
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
            App::response()->json(['ok' => false, 'error' => __('flash.not_found'), '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $result = $action === 'remove'
            ? App::snapshotService()->removeTag($repo, $snapId, $tag)
            : App::snapshotService()->addTag($repo, $snapId, $tag);

        $result['_csrf_token'] = App::security()->csrfToken();
        App::response()->json($result);
    }

    /**
     * POST /snapshots/copy — копирование снепшота в другой репозиторий (AJAX).
     */
    public function copy(): void
    {
        $auth = App::auth();
        $user = $auth->user();
        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();
        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => __('flash.csrf_error'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $sourceRepoId = $request->post('source_repo_id', '');
        $destRepoId = $request->post('dest_repo_id', '');
        $snapId = $request->post('snap_id', '');

        if ($sourceRepoId === '' || $destRepoId === '' || $snapId === '') {
            App::response()->json(['ok' => false, 'error' => 'Missing parameters', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        if ($sourceRepoId === $destRepoId) {
            App::response()->json(['ok' => false, 'error' => __('snap.copy_same_repo'), '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $sourceRepo = null;
        $destRepo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $sourceRepoId) {
                $sourceRepo = $r;
            }
            if (($r['id'] ?? '') === $destRepoId) {
                $destRepo = $r;
            }
        }

        if ($sourceRepo === null || $destRepo === null) {
            App::response()->json(['ok' => false, 'error' => __('flash.not_found'), '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $sourceCategory = $sourceRepo['category'] ?? 'public';
        $destCategory = $destRepo['category'] ?? 'public';
        if (!$auth->canUseRead($sourceCategory)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }
        if (!$auth->canUseWrite($destCategory)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        set_time_limit(0);
        $result = App::snapshotService()->copy($sourceRepo, $destRepo, $snapId);
        $result['_csrf_token'] = App::security()->csrfToken();
        App::response()->json($result);
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
