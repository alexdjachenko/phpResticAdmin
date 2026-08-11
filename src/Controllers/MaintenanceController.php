<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class MaintenanceController
{
    /**
     * GET /maintenance — страница с формами обслуживания.
     */
    public function index(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $this->resolveRepoId($request);

        if ($repoId === null) {
            App::session()->flash('success', __('flash.select_repo'));
            App::response()->redirect('/repositories');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('maintenance/index.php', [
            'repo' => $repo,
            'csrfToken' => $csrfToken,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /maintenance/check
     */
    public function check(): void
    {
        $this->runMaintenance('check', __('maint.check'));
    }

    /**
     * POST /maintenance/prune
     */
    public function prune(): void
    {
        $this->runMaintenance('prune', __('maint.prune'));
    }

    /**
     * POST /maintenance/rebuild-index
     */
    public function rebuildIndex(): void
    {
        $this->runMaintenance('rebuildIndex', __('maint.rebuild_index'));
    }

    /**
     * POST /maintenance/unlock
     */
    public function unlock(): void
    {
        $this->runMaintenance('unlock', __('maint.unlock'));
    }

    /**
     * POST /maintenance/init
     */
    public function init(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        if (!$auth->canInit()) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        if ($repoId === '') {
            App::response()->error(400, 'Missing repository ID');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::repoService()->init([
            'path' => $repo['path'],
            'password' => $repo['password'] ?? null,
            'env' => $repo['env'] ?? [],
        ]);

        echo App::response()->render('maintenance/result.php', [
            'action' => __('maint.init'),
            'result' => $result,
            'repo' => $repo,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /maintenance/forget
     */
    public function forget(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        if ($repoId === '') {
            App::response()->error(400, 'Missing repository ID');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $policy = [
            'keep_daily' => (int) $request->post('keep_daily', '0'),
            'keep_weekly' => (int) $request->post('keep_weekly', '0'),
            'keep_monthly' => (int) $request->post('keep_monthly', '0'),
            'keep_yearly' => (int) $request->post('keep_yearly', '0'),
            'keep_last' => (int) $request->post('keep_last', '0'),
            'prune' => $request->post('prune', '0') === '1',
            'dry_run' => $request->post('dry_run', '1') === '1',
        ];

        $result = App::maintenanceService()->forget($repo, $policy);

        echo App::response()->render('maintenance/result.php', [
            'action' => __('maint.forget'),
            'result' => $result,
            'repo' => $repo,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'dryRun' => $policy['dry_run'],
        ]);
    }

    private function runMaintenance(string $method, string $actionName): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->error(403, __('flash.csrf_error'));
            return;
        }

        $repoId = $request->post('repo_id', '');
        if ($repoId === '') {
            App::response()->error(400, 'Missing repository ID');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUseWrite($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $result = App::maintenanceService()->$method($repo);

        echo App::response()->render('maintenance/result.php', [
            'action' => $actionName,
            'result' => $result,
            'repo' => $repo,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    private function resolveRepoId(Request $request): ?string
    {
        $repoId = $request->get('repo', '');
        if ($repoId !== '') {
            return $repoId;
        }
        $sessionRepoId = App::session()->get('current_repo');
        if ($sessionRepoId !== null) {
            return $sessionRepoId;
        }
        return null;
    }
}
