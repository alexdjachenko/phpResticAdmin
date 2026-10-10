<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Maintenance;

use App\Controllers\BaseController;
use App\Core\App;

class IndexController extends BaseController
{
    /**
     * GET /maintenance — страница с формами обслуживания.
     */
    public function index(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = $this->resolveRepoId($this->request());

        if ($repoId === null) {
            App::session()->flash('success', __('flash.select_repo'));
            App::response()->redirect('/repositories');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $this->render('maintenance/index.php', [
            'repo' => $repo,
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /maintenance/connection — быстрая проверка доступности
     * репозитория через restic cat config.
     */
    public function connection(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/maintenance')) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            App::response()->error(400, 'Missing repository ID');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $result = App::repoService()->testConnection($repo);

        $this->render('maintenance/result.php', [
            'action' => __('maint.check_connection'),
            'result' => $result,
            'repo' => $repo,
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }
}
