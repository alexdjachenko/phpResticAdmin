<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Snapshot;

use App\Controllers\BaseController;
use App\Core\App;

class ListController extends BaseController
{
    /**
     * GET /snapshots — список снепшотов (автомат list/progress/error/start).
     */
    public function list(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = $this->resolveRepoId($this->request());

        if ($repoId === null) {
            $this->render('snapshots/list.php', [
                'repo' => null,
                'view' => ['state' => 'list', 'snapshots' => []],
                'isLoggedIn' => App::auth()->isLoggedIn(),
                'username' => $user,
            ]);
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $privileged = App::auth()->canManageProcesses();
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

        $this->render('snapshots/list.php', [
            'repo' => $repo,
            'view' => $view,
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
            'csrfToken' => App::security()->csrfToken(),
        ]);
    }
}
