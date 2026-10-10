<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Core\App;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $auth = App::auth();
        $repositories = App::repoStorage()->loadAll($user);
        $currentRepoId = App::session()->get('current_repo');
        $repo = null;
        $latestSnapshots = [];
        $needLoad = false;

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
                // «Последние 5» режем в PHP из кешированного списка (семантика
                // «5 всего», а не «5 на каждую пару host+path»).
                $allSnapshots = App::snapshotCache()->list($repo);
                if ($allSnapshots === null) {
                    $needLoad = true;
                } else {
                    usort($allSnapshots, static function (array $a, array $b): int {
                        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
                    });
                    $latestSnapshots = array_slice($allSnapshots, 0, 5);
                }
            }
        }

        $tasks = App::tasks()->listForUser($user, $auth->canManageProcesses());
        foreach ($tasks as &$task) {
            $label = (string) ($task['label'] ?? '');
            $described = $label !== '' ? App::tasks()->describe($label) : null;
            $task['title'] = $described['title'] ?? ($label !== '' ? $label : '—');
        }
        unset($task);

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

        $this->render('dashboard.php', [
            'repo' => $repo,
            'latestSnapshots' => $latestSnapshots,
            'needLoad' => $needLoad,
            'repoCount' => $repoCount,
            'repoStats' => $repoStats,
            'activeTasks' => $activeTasks,
            'recentTasks' => $recentTasks,
        ]);
    }

    public function invalidateCache(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!App::isDebug()) {
            $this->jsonError('Debug mode is disabled', 403);
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $result = App::invalidateCaches();

        // Сбрасываем область текущего запроса (мемоизации tsp/настроек/окружения).
        // Системные записи (списки/статистика снепшотов) НЕ сносим: иначе кнопка
        // вызывала бы лавину обращений к restic.
        App::resetCaches();

        App::log('Cache invalidated: ' . $result['count'] . ' scripts cleared', 0);

        $this->jsonOk($result);
    }
}
