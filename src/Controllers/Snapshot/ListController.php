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
use App\Storage\ContentTargets;

class ListController extends BaseController
{
    /**
     * GET /snapshots — список снепшотов.
     *
     * Только чтение: read() + reconcile() (постобработка УЖЕ готового вывода).
     * Старт задачи — отдельная операция POST /snapshots/load (кнопка). Раньше
     * страница сама блокирующе запускала задачу и ждала результат — из-за этого
     * зависал сервер на больших репозиториях.
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
                'view' => ['state' => 'absent'],
                'isLoggedIn' => App::auth()->isLoggedIn(),
                'username' => $user,
            ]);
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $resolver = App::contentResolver();
        $target = ContentTargets::forSnapshots($repo);

        // Постобработка уже завершённого вывода (задач НЕ ставит).
        $resolver->reconcile($target);
        $view = $resolver->read($target);

        $this->render('snapshots/list.php', [
            'repo' => $repo,
            'view' => $view,
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
            'csrfToken' => App::security()->csrfToken(),
        ]);
    }
}
