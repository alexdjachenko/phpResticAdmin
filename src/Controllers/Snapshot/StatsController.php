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

class StatsController extends BaseController
{
    /**
     * POST /snapshots/stats — запуск фоновой задачи статистики (AJAX).
     *
     * Явный старт: schedule() ставит задачу и записывает обещание в запись
     * статистики. Если активная задача по этому снепшоту уже есть — вторую не ставит.
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

        $target = ContentTargets::forStats($repoId, $snapId);
        $started = App::contentResolver()->schedule($target, $repo);

        // respondTaskStarted() сам вернёт ошибку, если задача не поставлена.
        // В заголовке указываем источник (репозиторий снепшота).
        $this->respondTaskStarted($started, $this->taskTitle('tasks.op_snapstats', $repo));
    }

    /**
     * GET /snapshots/stats/result — результат задачи статистики.
     *
     * Доводит обработку до конца (reconcile) и отдаёт готовую статистику из
     * кеша. Задач не ставит — только разбирает уже завершённый вывод.
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

        $resolver = App::contentResolver();
        $resolver->reconcileByLabel($label);

        $target = ContentTargets::forStats((string) ($parsed['repoId'] ?? ''), $snapId);
        $read = $resolver->read($target);

        if ($read['state'] === 'ready' && is_array($read['value'])) {
            $this->jsonOk([
                'stats' => $read['value'],
                'computed_at' => (int) ($read['cached_at'] ?? time()),
            ]);
            return;
        }

        // Показываем настоящую причину из записи кеша, а не общее сообщение.
        $detail = trim((string) ($read['error'] ?? ''));
        $this->jsonError($detail !== '' ? $detail : __('snap.stats_failed'), 200);
    }
}
