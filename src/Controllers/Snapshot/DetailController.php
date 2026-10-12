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

class DetailController extends BaseController
{
    /**
     * GET /snapshots/detail — страница снепшота со сводкой и статистикой.
     */
    public function detail(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = (string) $this->request()->get('repo', '');
        $snapId = (string) $this->request()->get('snapshot', '');

        if ($repoId === '' || $snapId === '') {
            App::response()->redirect('/snapshots');
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $snap = App::snapshotService()->getSnapshot($repo, $snapId);
        if ($snap === null) {
            App::response()->error(404, __('flash.not_found'));
        }

        $auth = App::auth();
        $repositories = App::repoStorage()->loadAll($user);
        $destRepos = [];
        foreach ($repositories as $r) {
            $cat = $r['category'] ?? 'public';
            if (($r['id'] ?? '') !== $repoId && $auth->canUseWrite($cat)) {
                $destRepos[] = ['id' => $r['id'], 'name' => $r['name']];
            }
        }

        // Статистика — та же модель обещания: reconcile() разбирает уже готовый
        // вывод завершённой задачи, read() отдаёт состояние записи кеша.
        $statsTarget = ContentTargets::forStats($repoId, $snapId);
        $resolver = App::contentResolver();
        $resolver->reconcile($statsTarget);
        $statsRead = $resolver->read($statsTarget);

        $statsEntry = null;
        if ($statsRead['state'] === 'ready' && is_array($statsRead['value'])) {
            $statsEntry = [
                'computed_at' => (int) ($statsRead['cached_at'] ?? time()),
                'stats' => $statsRead['value'],
                'stale' => false,
            ];
        }

        $this->render('snapshots/detail.php', [
            'repo' => $repo,
            'snap' => $snap,
            'csrfToken' => App::security()->csrfToken(),
            'destRepos' => $destRepos,
            'statsEntry' => $statsEntry,
            'statsState' => $statsRead['state'],
            'statsLabel' => $statsRead['promise'] ?? null,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }
}
