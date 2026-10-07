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
                'repo' => null,
                'view' => ['state' => 'list', 'snapshots' => []],
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

        $privileged = $auth->canManageProcesses();
        $view = App::snapshotListState()->resolve($repo, $user, $privileged);

        // Автомат решил, что нужен старт: ставим задачу и фиксируем её метку.
        if ($view['state'] === 'start') {
            $started = App::resticTasks()->startListSnapshots($repo);
            if ($started['id'] < 0) {
                App::snapshotCache()->setListError($repo, __('snap.load_error'));
                $view = ['state' => 'error', 'error' => __('snap.load_error'), 'task_label' => null];
            } else {
                App::snapshotCache()->markTask($repo, $started['label']);
                $view = ['state' => 'progress', 'task_label' => $started['label'], 'task_state' => 'queued', 'position' => null];
            }
        }

        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('snapshots/list.php', [
            'repo' => $repo,
            'view' => $view,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'csrfToken' => $csrfToken,
        ]);
        }

    /**
     * POST /snapshots/refresh — сброс кеша списка снепшотов.
     */
    public function refresh(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        if (!$security->validateCsrf($request->post('_csrf_token', ''))) {
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/snapshots');
            return;
        }

        $repoId = (string) $request->post('repo_id', '');
        if ($repoId === '') {
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

        if (!$auth->canUseRead($repo['category'] ?? 'public')) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        // Инвалидация записи: задачу поставит list() при следующем заходе
        // (refresh = «снять запись», а не «запустить задачу самому»).
        App::snapshotCache()->invalidateList($repo);

        App::response()->redirect('/snapshots?repo=' . urlencode($repoId), 303);
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
            'statsEntry' => App::snapshotCache()->statsEntry($snapId),
            'statsTtl' => (int) (App::configStorage()->loadSettings()['snapshot_stats_cache_ttl'] ?? 31536000),
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

        $started = App::resticTasks()->startSnapshotStats($repo, $snapId);

        App::response()->json([
            'ok' => true,
            'label' => $started['label'],
            'title' => __('tasks.op_snapstats'),
            'stream_url' => '/tasks/stream?label=' . urlencode($started['label']),
            '_csrf_token' => App::security()->csrfToken(),
        ]);
        }

        /**
        * GET /snapshots/stats/result — результат задачи статистики.
        *
        * Проверяет, что метка принадлежит пользователю и её op = snapstats с этим
        * id снепшота, читает вывод задачи, парсит и кладёт в системную область.
        */
        public function statsResult(): void
        {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required'], 403);
            return;
        }

        $request = new Request();
        $label = (string) $request->get('label', '');
        $snapId = (string) $request->get('snap_id', '');
        $tasks = App::tasks();
        $privileged = $auth->canManageProcesses();

        if ($label === '' || $snapId === '' || !$tasks->assertAccess($user, $label, $privileged)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid task label'], 400);
            return;
        }

        $parsed = $tasks->parseLabel($label);
        if ($parsed === null || $parsed['op'] !== 'snapstats') {
            App::response()->json(['ok' => false, 'error' => 'Not a snapshot stats task'], 400);
            return;
        }

        $result = $tasks->catResult($user, $label, $privileged);
        if ($result === null || $result['exitCode'] !== 0) {
            App::response()->json(['ok' => false, 'error' => __('snap.stats_failed')], 200);
            return;
        }

        $stats = \App\Restic\SnapshotService::parseStatsOutput($result['output']);
        if ($stats === null) {
            App::response()->json(['ok' => false, 'error' => __('snap.stats_failed')], 200);
            return;
        }

        App::snapshotCache()->setStats($snapId, $stats);

        App::response()->json([
            'ok' => true,
            'stats' => $stats,
            'computed_at' => time(),
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

        $started = App::resticTasks()->startSnapshotCopy($sourceRepo, $destRepo, $snapId);

        App::response()->json([
            'ok' => true,
            'label' => $started['label'],
            'title' => __('tasks.op_copysnap'),
            'stream_url' => '/tasks/stream?label=' . urlencode($started['label']),
            '_csrf_token' => App::security()->csrfToken(),
        ]);
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
