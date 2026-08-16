<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class DashboardController
{
    public function index(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $currentRepoId = App::session()->get('current_repo');
        $repo = null;
        $latestSnapshots = [];

        $visibleRepositories = [];
        foreach ($repositories as $r) {
            if ($auth->canUse($r['category'] ?? 'public')) {
                $visibleRepositories[] = $r;
            }
        }

        $repoStats = ['public' => 0, 'private' => 0, 'session' => 0, 'total' => 0];
        foreach ($visibleRepositories as $r) {
            $cat = $r['category'] ?? 'public';
            if (isset($repoStats[$cat])) {
                $repoStats[$cat]++;
            }
            $repoStats['total']++;
        }
        $repoCount = $repoStats['total'];

        if ($currentRepoId !== null) {
            foreach ($repositories as $r) {
                if (($r['id'] ?? '') === $currentRepoId) {
                    $category = $r['category'] ?? 'public';
                    if ($auth->canUse($category)) {
                        $repo = $r;
                    }
                    break;
                }
            }

            if ($repo !== null) {
                $latestSnapshots = App::snapshotService()->listLatestSnapshots($repo, 5);
            }
        }

        $tasks = App::tasks()->listForUser($user, $auth->canManageProcesses());

        $activeTasks = [];
        $recentTasks = [];
        foreach ($tasks as $task) {
            $state = $task['state'] ?? '';
            if (in_array($state, ['queued', 'running'], true)) {
                $activeTasks[] = $task;
            } elseif ($state === 'finished') {
                $recentTasks[] = $task;
            }
        }
        $recentTasks = array_slice(array_reverse($recentTasks), 0, 10);

        echo App::response()->render('dashboard.php', [
            'repo' => $repo,
            'latestSnapshots' => $latestSnapshots,
            'repoCount' => $repoCount,
            'repoStats' => $repoStats,
            'activeTasks' => $activeTasks,
            'recentTasks' => $recentTasks,
        ]);
        }

    public function invalidateCache(): void
    {
        $auth = App::auth();
        if (!$auth->isLoggedIn()) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required'], 403);
            return;
        }

        if (!App::isDebug()) {
            App::response()->json(['ok' => false, 'error' => 'Debug mode is disabled'], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid security token', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $result = App::invalidateCaches();
        $result['ok'] = true;
        $result['_csrf_token'] = App::security()->csrfToken();

        App::log('Cache invalidated: ' . $result['count'] . ' scripts cleared', 0);

        App::response()->json($result);
    }
}
