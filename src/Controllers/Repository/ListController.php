<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Repository;

use App\Controllers\BaseController;
use App\Core\App;

class ListController extends BaseController
{
    /**
     * GET /repositories — список доступных репозиториев.
     */
    public function list(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $auth = App::auth();
        $allRepositories = App::repoStorage()->loadAll($user);
        $repositories = [];
        foreach ($allRepositories as $repo) {
            if ($auth->canUse($repo['category'] ?? 'public')) {
                $repositories[] = $repo;
            }
        }

        $flash = App::session()->flash('success');

        $availableCategories = [];
        foreach (['public', 'private', 'session'] as $cat) {
            if ($auth->canEdit($cat)) {
                $availableCategories[$cat] = __('repo.category.' . $cat);
            }
        }

        $canAdd = !empty($availableCategories);

        $this->render('repositories/list.php', [
            'repositories' => $repositories,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'flash' => $flash,
            'canAdd' => $canAdd,
        ]);
    }
}
