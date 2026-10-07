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

class DetailController extends BaseController
{
    /**
     * GET /repositories/detail — страница деталей репозитория.
     */
    public function detail(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = (string) $this->request()->get('repo', '');
        if ($repoId === '') {
            App::response()->redirect('/repositories');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $auth = App::auth();
        $category = $repo['category'] ?? 'public';

        $canEdit = $auth->canEdit($category);
        $canUseWrite = $auth->canUseWrite($category);
        $canDelete = $auth->canDelete();
        $canBackup = $canUseWrite && !empty($repo['backup_paths']);

        $availableCategories = [];
        foreach (['public', 'private', 'session'] as $cat) {
            if ($cat !== $category && $auth->canMove($category, $cat)) {
                $availableCategories[$cat] = __('repo.category.' . $cat);
            }
        }
        $canMove = !empty($availableCategories);

        // «Последние 5» режем в PHP из кешированного списка: `restic snapshots
        // --latest 5` означал бы «5 на каждую пару host+path», а не «5 всего».
        $allSnapshots = App::snapshotCache()->list($repo);
        $latestSnapshots = $allSnapshots !== null ? self::lastSnapshots($allSnapshots, 5) : [];

        $this->render('repositories/detail.php', [
            'repo' => $repo,
            'category' => $category,
            'canEdit' => $canEdit,
            'canUseWrite' => $canUseWrite,
            'canDelete' => $canDelete,
            'canBackup' => $canBackup,
            'canMove' => $canMove,
            'availableCategories' => $availableCategories,
            'latestSnapshots' => $latestSnapshots,
            'hasMoreSnapshots' => $allSnapshots !== null && count($allSnapshots) > 5,
            'needLoad' => $allSnapshots === null,
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * Последние N снепшотов по времени (desc) из полного списка.
     *
     * @param array<int, array<string, mixed>> $snapshots
     * @return array<int, array<string, mixed>>
     */
    private static function lastSnapshots(array $snapshots, int $n): array
    {
        usort($snapshots, static function (array $a, array $b): int {
            return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
        });

        return array_slice($snapshots, 0, $n);
    }
}
