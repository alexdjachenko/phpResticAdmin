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
use App\Process\TaskLabel;

class ActionController extends BaseController
{
    /**
     * POST /maintenance/init
     */
    public function init(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!App::auth()->canInit()) {
            $this->abort(403, false);
            return;
        }

        if (!$this->requireCsrf(false, '/maintenance')) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            $this->abort(400, false, 'Missing repository ID');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $started = App::resticTasks()->startInit($repo);

        $this->respondTaskStarted($started, __('tasks.op_init'), false, '/maintenance');
    }

    /**
     * POST /maintenance/check
     */
    public function check(): void
    {
        $this->runMaintenance('check');
    }

    /**
     * POST /maintenance/prune
     */
    public function prune(): void
    {
        $this->runMaintenance('prune');
    }

    /**
     * POST /maintenance/rebuild-index
     */
    public function rebuildIndex(): void
    {
        $this->runMaintenance('repair index');
    }

    /**
     * POST /maintenance/unlock
     */
    public function unlock(): void
    {
        $this->runMaintenance('unlock');
    }

    /**
     * POST /maintenance/stats — общая статистика репозитория (restic stats).
     */
    public function stats(): void
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
            $this->abort(400, false, 'Missing repository ID');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $started = App::resticTasks()->startMaintenance('stats', $repo);

        $this->respondTaskStarted($started, $this->operationTitle('stats'), false, '/maintenance');
    }

    /**
     * POST /maintenance/forget
     */
    public function forget(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/maintenance')) {
            return;
        }

        $request = $this->request();

        $repoId = (string) $request->post('repo_id', '');
        if ($repoId === '') {
            $this->abort(400, false, 'Missing repository ID');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $policy = [
            'keep_daily' => (int) $request->post('keep_daily', '0'),
            'keep_weekly' => (int) $request->post('keep_weekly', '0'),
            'keep_monthly' => (int) $request->post('keep_monthly', '0'),
            'keep_yearly' => (int) $request->post('keep_yearly', '0'),
            'keep_last' => (int) $request->post('keep_last', '0'),
            'prune' => $request->post('prune', '0') === '1',
            'dry_run' => $request->post('dry_run', '0') === '1',
        ];

        $started = App::resticTasks()->startMaintenance('forget', $repo, $policy);

        $this->respondTaskStarted($started, $this->operationTitle('forget'), (bool) $policy['dry_run'], '/maintenance');
    }

    /**
     * Общий запуск фоновой задачи обслуживания (check/prune/rebuild/unlock).
     */
    private function runMaintenance(string $operation): void
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
            $this->abort(400, false, 'Missing repository ID');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $started = App::resticTasks()->startMaintenance($operation, $repo);

        $this->respondTaskStarted($started, $this->operationTitle($operation), false, '/maintenance');
    }

    private function operationTitle(string $operation): string
    {
        return __('tasks.op_' . TaskLabel::opForOperation($operation));
    }
}
