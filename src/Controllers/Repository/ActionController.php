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
use App\Helpers\RepositoryPath;

class ActionController extends BaseController
{
    /**
     * POST /repositories/check — быстрая проверка доступности (JSON).
     */
    public function check(): void
    {
        if (!App::auth()->isLoggedIn()) {
            $this->jsonError('Authentication required', 403);
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            $this->jsonError('Repository ID is required', 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $repo = $this->requireRepo($user, $repoId, 'read', true);
        if ($repo === null) {
            return;
        }

        $this->jsonOk(App::repoService()->testConnection($repo));
    }

    /**
     * POST /repositories/delete — удаление репозитория.
     */
    public function delete(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            $this->jsonError('Repository ID is required', 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $repo = $this->findRepo($user, $repoId);
        if ($repo === null) {
            $this->jsonError(__('flash.not_found'), 404);
            return;
        }

        if (!App::auth()->canDelete()) {
            $this->jsonError(__('error.forbidden'), 403);
            return;
        }

        App::repoStorage()->delete((string) ($repo['category'] ?? 'public'), $repoId, $user);

        // Снимаем кеш списка снепшотов репозитория (каталог данных уходит вместе с ним).
        App::snapshotCache()->invalidateList($repo);

        // Сбросить current_repo если удалённый id совпадает
        if (App::session()->get('current_repo') === $repoId) {
            App::session()->remove('current_repo');
        }

        App::session()->flash('success', __('flash.repo_deleted'));
        $this->jsonOk(['redirect' => '/repositories']);
    }

    /**
     * POST /repositories/move — перенос между категориями.
     */
    public function move(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        $toCategory = (string) $this->request()->post('to_category', '');

        if ($repoId === '' || $toCategory === '') {
            $this->jsonError('Repository ID and target category are required', 400);
            return;
        }

        if (!in_array($toCategory, ['public', 'private', 'session'], true)) {
            $this->jsonError('Invalid category', 400);
            return;
        }

        $user = App::auth()->user() ?? '';
        $found = $this->findRepo($user, $repoId);
        if ($found === null) {
            $this->jsonError(__('flash.not_found'), 404);
            return;
        }

        $fromCategory = $found['category'] ?? 'public';

        if ($fromCategory === $toCategory) {
            $this->jsonError('Repository is already in this category', 400);
            return;
        }

        if (!App::auth()->canMove($fromCategory, $toCategory)) {
            $this->jsonError(__('error.forbidden'), 403);
            return;
        }

        App::repoStorage()->move($repoId, $fromCategory, $toCategory, $user);

        $fromLabel = __('repo.category.' . $fromCategory);
        $toLabel = __('repo.category.' . $toCategory);
        App::session()->flash('success', __('flash.repo_moved', ['{from}' => $fromLabel, '{to}' => $toLabel]));

        $this->jsonOk(['redirect' => '/repositories']);
    }

    /**
     * POST /repositories/backup — запуск restic backup в фоне.
     */
    public function backup(): void
    {
        if ($this->requireUser(true) === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/repositories')) {
            return;
        }

        $user = App::auth()->user() ?? '';
        $repoId = (string) $this->request()->get('repo', '');
        $repo = $this->requireRepo($user, $repoId, 'write');
        if ($repo === null) {
            return;
        }

        $backupPaths = $repo['backup_paths'] ?? [];
        if (empty($backupPaths)) {
            $this->redirectOrJson(__('repo.no_backup_paths'), '/repositories/detail?repo=' . urlencode($repoId));
            return;
        }

        $settings = App::configStorage()->loadSettings();
        $disallowedBackup = RepositoryPath::firstDisallowedBackupPath($backupPaths, $settings['backup_paths_roots'] ?? []);
        if ($disallowedBackup !== null) {
            $this->redirectOrJson(__('repo.backup_path_outside_roots', ['{roots}' => implode(', ', $settings['backup_paths_roots'] ?? [])]), '/repositories/detail?repo=' . urlencode($repoId));
            return;
        }

        $started = App::resticTasks()->startBackup($repo, $backupPaths);

        $this->respondTaskStarted($started, __('tasks.op_backup'), false, '/repositories/detail?repo=' . urlencode($repoId));
    }

    /**
     * POST /repositories/select — выбор текущего репозитория (без CSRF).
     */
    public function select(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = (string) $this->request()->post('repo_id', '');

        if ($repoId === '') {
            App::session()->remove('current_repo');
        } else {
            $repo = $this->findRepo($user, $repoId);
            if ($repo !== null && App::auth()->canUse((string) ($repo['category'] ?? 'public'))) {
                App::session()->set('current_repo', $repoId);
            }
        }

        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
