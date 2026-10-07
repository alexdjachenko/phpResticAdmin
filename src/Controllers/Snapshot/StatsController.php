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
use App\Restic\SnapshotService;

class StatsController extends BaseController
{
    /**
     * POST /snapshots/stats — запуск фоновой задачи статистики (AJAX).
     */
    public function stats(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        $snapId = (string) $this->request()->post('snap_id', '');

        if ($repoId === '' || $snapId === '') {
            $this->jsonError('Missing parameters', 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $repo = $this->requireRepo($user, $repoId, 'read', true);
        if ($repo === null) {
            return;
        }

        $started = App::resticTasks()->startSnapshotStats($repo, $snapId);

        $this->jsonOk([
            'label' => $started['label'],
            'title' => __('tasks.op_snapstats'),
            'stream_url' => '/tasks/stream?label=' . urlencode($started['label']),
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
        $user = $this->requireUser(true);
        if ($user === null) {
            return;
        }

        $label = (string) $this->request()->get('label', '');
        $snapId = (string) $this->request()->get('snap_id', '');

        $tasks = App::tasks();
        $privileged = App::auth()->canManageProcesses();

        if ($label === '' || $snapId === '' || !$tasks->assertAccess($user, $label, $privileged)) {
            $this->jsonError('Invalid task label', 400);
            return;
        }

        $parsed = $tasks->parseLabel($label);
        if ($parsed === null || $parsed['op'] !== 'snapstats') {
            $this->jsonError('Not a snapshot stats task', 400);
            return;
        }

        $result = $tasks->catResult($user, $label, $privileged);
        if ($result === null || $result['exitCode'] !== 0) {
            $this->jsonError(__('snap.stats_failed'), 200);
            return;
        }

        $stats = SnapshotService::parseStatsOutput($result['output']);
        if ($stats === null) {
            $this->jsonError(__('snap.stats_failed'), 200);
            return;
        }

        App::snapshotCache()->setStats($snapId, $stats);

        $this->jsonOk([
            'stats' => $stats,
            'computed_at' => time(),
        ]);
    }
}
