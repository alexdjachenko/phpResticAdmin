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

class ActionController extends BaseController
{
    /**
     * POST /snapshots/tag — тегирование (AJAX).
     */
    public function tag(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $request = $this->request();
        $repoId = (string) $request->post('repo_id', '');
        $snapId = (string) $request->post('snap_id', '');
        $tag = (string) $request->post('tag', '');
        $action = (string) $request->post('action', 'add');

        if ($repoId === '' || $snapId === '' || $tag === '') {
            $this->jsonError('Missing parameters', 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $repo = $this->requireRepo($user, $repoId, 'write', true);
        if ($repo === null) {
            return;
        }

        $result = $action === 'remove'
            ? App::snapshotService()->removeTag($repo, $snapId, $tag)
            : App::snapshotService()->addTag($repo, $snapId, $tag);

        // Теги входят в кешированный список — снимаем запись, чтобы UI увидел тег.
        if (!empty($result['ok'])) {
            App::snapshotCache()->invalidateList($repo);
        }

        $this->jsonOk($result);
    }

    /**
     * POST /snapshots/copy — копирование снепшота в другой репозиторий (AJAX).
     */
    public function copy(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $request = $this->request();
        $sourceRepoId = (string) $request->post('source_repo_id', '');
        $destRepoId = (string) $request->post('dest_repo_id', '');
        $snapId = (string) $request->post('snap_id', '');

        if ($sourceRepoId === '' || $destRepoId === '' || $snapId === '') {
            $this->jsonError('Missing parameters', 400);
            return;
        }

        if ($sourceRepoId === $destRepoId) {
            $this->jsonError(__('snap.copy_same_repo'), 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $sourceRepo = $this->findRepo($user, $sourceRepoId);
        $destRepo = $this->findRepo($user, $destRepoId);

        if ($sourceRepo === null || $destRepo === null) {
            $this->jsonError(__('flash.not_found'), 404);
            return;
        }

        $auth = App::auth();
        if (!$auth->canUseRead((string) ($sourceRepo['category'] ?? 'public'))) {
            $this->jsonError(__('error.forbidden'), 403);
            return;
        }
        if (!$auth->canUseWrite((string) ($destRepo['category'] ?? 'public'))) {
            $this->jsonError(__('error.forbidden'), 403);
            return;
        }

        $started = App::resticTasks()->startSnapshotCopy($sourceRepo, $destRepo, $snapId);

        $this->jsonOk([
            'label' => $started['label'],
            'title' => __('tasks.op_copysnap'),
            'stream_url' => '/tasks/stream?label=' . urlencode($started['label']),
        ]);
    }

    /**
     * POST /snapshots/refresh — инвалидация кеша списка снепшотов.
     */
    public function refresh(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/snapshots')) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            App::response()->redirect('/snapshots');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        // Инвалидация записи: задачу поставит list() при следующем заходе
        // (refresh = «снять запись», а не «запустить задачу самому»).
        App::snapshotCache()->invalidateList($repo);

        App::response()->redirect('/snapshots?repo=' . urlencode($repoId), 303);
    }
}
