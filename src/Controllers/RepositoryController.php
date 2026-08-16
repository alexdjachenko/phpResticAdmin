<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Helpers\RepositoryPath;

class RepositoryController
{
    public function list(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

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

        echo App::response()->render('repositories/list.php', [
            'repositories' => $repositories,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'flash' => $flash,
            'canAdd' => $canAdd,
        ]);
    }

    public function check(): void
    {
        $auth = App::auth();
        if (!$auth->isLoggedIn()) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid security token', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $repoId = $request->post('repo_id', '');

        if ($repoId === '') {
            App::response()->json(['ok' => false, 'error' => 'Repository ID is required', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        $user = $auth->user();
        $repositories = App::repoStorage()->loadAll($user ?? '');
        $repository = null;

        foreach ($repositories as $repo) {
            if (($repo['id'] ?? '') === $repoId) {
                $repository = $repo;
                break;
            }
        }

        if ($repository === null) {
            App::response()->json(['ok' => false, 'error' => 'Repository not found', '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $category = $repository['category'] ?? 'public';
        if (!$auth->canUseRead($category)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $result = App::repoService()->testConnection($repository);
        $result['_csrf_token'] = App::security()->csrfToken();
        App::response()->json($result);
    }

    /**
     * GET /repositories/add — форма добавления.
     */
    public function addForm(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $availableCategories = [];
        foreach (['public', 'private', 'session'] as $cat) {
            if ($auth->canEdit($cat)) {
                $availableCategories[$cat] = __('repo.category.' . $cat);
            }
        }

        if (empty($availableCategories)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $flash = App::session()->flash('error');
        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('repositories/add.php', [
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'csrfToken' => $csrfToken,
            'categories' => $availableCategories,
            'canInit' => $auth->canInit(),
            'error' => $flash,
        ]);
    }

    /**
     * GET /repositories/detail — страница деталей репозитория.
     */
    public function detail(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $request->get('repo', '');

        if ($repoId === '') {
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

        if (!$auth->canUseRead($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

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

        $latestSnapshots = App::snapshotService()->listLatestSnapshots($repo, 5);

        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('repositories/detail.php', [
            'repo' => $repo,
            'category' => $category,
            'canEdit' => $canEdit,
            'canUseWrite' => $canUseWrite,
            'canDelete' => $canDelete,
            'canBackup' => $canBackup,
            'canMove' => $canMove,
            'availableCategories' => $availableCategories,
            'latestSnapshots' => $latestSnapshots,
            'hasMoreSnapshots' => count($latestSnapshots) >= 5,
            'csrfToken' => $csrfToken,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * GET /repositories/edit — форма редактирования.
     */
    public function editForm(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $request->get('repo', '');

        if ($repoId === '') {
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

        if (!$auth->canEdit($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $flash = App::session()->flash('error');
        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('repositories/edit.php', [
            'repo' => $repo,
            'category' => $category,
            'error' => $flash,
            'csrfToken' => $csrfToken,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /repositories/edit — сохранение изменений.
     */
    public function edit(): void
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
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/repositories');
            return;
        }

        $repoId = $request->post('repo_id', '');
        $name = trim($request->post('name', ''));
        $type = $request->post('type', 'local');
        $password = $request->post('password', '');
        $backupPathsRaw = $request->post('backup_paths', '');
        $s3Key = trim($request->post('s3_key', ''));
        $s3Secret = trim($request->post('s3_secret', ''));
        $s3Endpoint = trim($request->post('s3_endpoint', ''));

        $locationField = $this->locationFieldFor($type);
        $locationValue = trim($request->post($locationField, ''));

        if ($name === '' || $locationValue === '') {
            App::session()->flash('error', __('repo.name_path_required'));
            App::response()->redirect('/repositories/edit?repo=' . urlencode($repoId));
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $found = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $found = $r;
                break;
            }
        }

        if ($found === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $found['category'] ?? 'public';

        if (!$auth->canEdit($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $settings = App::configStorage()->loadSettings();

        $location = RepositoryPath::normalize($type, $locationValue, $settings['repo_base_dir'] ?? null);

        if ($type === 'local' && !RepositoryPath::localRepoAllowed($location, $settings['repo_paths_roots'] ?? [])) {
            App::session()->flash('error', __('repo.path_outside_roots', ['{roots}' => implode(', ', $settings['repo_paths_roots'] ?? [])]));
            App::response()->redirect('/repositories/edit?repo=' . urlencode($repoId));
            return;
        }

        $backupPaths = array_values(array_filter(
            array_map('trim', explode("\n", $backupPathsRaw)),
            function (string $p): bool { return $p !== ''; }
        ));

        $disallowedBackup = RepositoryPath::firstDisallowedBackupPath($backupPaths, $settings['backup_paths_roots'] ?? []);
        if ($disallowedBackup !== null) {
            App::session()->flash('error', __('repo.backup_path_outside_roots', ['{roots}' => implode(', ', $settings['backup_paths_roots'] ?? [])]));
            App::response()->redirect('/repositories/edit?repo=' . urlencode($repoId));
            return;
        }

        $newData = [
            'name' => $name,
            'type' => $type,
            'local_path' => null,
            's3_bucket' => null,
            'sftp_path' => null,
            'rest_url' => null,
            'path' => null,
        ];
        $newData[$locationField] = $location;

        if ($password !== '') {
            $newData['password'] = $password;
        }

        if (!empty($backupPaths)) {
            $newData['backup_paths'] = $backupPaths;
        } else {
            $newData['backup_paths'] = null;
        }

        // Env (S3-ключи) сохраняются даже при смене типа с s3 на local:
        // если пользователь передумает и вернётся к s3, данные не потеряются.
        if ($found['type'] === 's3' || $type === 's3') {
            $env = $found['env'] ?? [];
            if ($s3Key !== '') {
                $env['AWS_ACCESS_KEY_ID'] = $s3Key;
            }
            if ($s3Secret !== '') {
                $env['AWS_SECRET_ACCESS_KEY'] = $s3Secret;
            }
            if ($s3Endpoint !== '') {
                $env['AWS_ENDPOINT'] = $s3Endpoint;
            } else {
                unset($env['AWS_ENDPOINT']);
            }
            $newData['env'] = !empty($env) ? $env : null;
        }

        App::repoStorage()->update($category, $repoId, $newData, $user);
        App::session()->flash('success', __('repo.updated'));
        App::response()->redirect('/repositories/detail?repo=' . urlencode($repoId));
    }

    /**
     * POST /repositories/backup — запуск restic backup с показом вывода.
     */
    public function backup(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->redirect('/repositories');
            return;
        }

        $repoId = $request->get('repo', '');

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

        $backupPaths = $repo['backup_paths'] ?? [];
        if (empty($backupPaths)) {
            App::session()->flash('error', __('repo.no_backup_paths'));
            App::response()->redirect('/repositories/detail?repo=' . urlencode($repoId));
            return;
        }

        $settings = App::configStorage()->loadSettings();
        $disallowedBackup = RepositoryPath::firstDisallowedBackupPath($backupPaths, $settings['backup_paths_roots'] ?? []);
        if ($disallowedBackup !== null) {
            App::session()->flash('error', __('repo.backup_path_outside_roots', ['{roots}' => implode(', ', $settings['backup_paths_roots'] ?? [])]));
            App::response()->redirect('/repositories/detail?repo=' . urlencode($repoId));
            return;
        }

        $started = App::resticTasks()->startBackup($repo, $backupPaths);

        App::response()->redirect('/tasks/stream?label=' . urlencode($started['label']));
        }

    /**
     * POST /repositories/select — выбор текущего репозитория (без CSRF).
     */
    public function select(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $request->post('repo_id', '');

        if ($repoId === '') {
            App::session()->remove('current_repo');
        } else {
            $repositories = App::repoStorage()->loadAll($user);
            $found = false;
            foreach ($repositories as $r) {
                if (($r['id'] ?? '') === $repoId) {
                    $category = $r['category'] ?? 'public';
                    if ($auth->canUse($category)) {
                        $found = true;
                    }
                    break;
                }
            }
            if ($found) {
                App::session()->set('current_repo', $repoId);
            }
        }

        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /**
     * POST /repositories/add — сохранение + опционально restic init.
     */
    public function add(): void
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
            App::session()->flash('error', __('flash.csrf_error'));
            App::response()->redirect('/repositories/add');
            return;
        }

        $name = trim($request->post('name', ''));
        $type = $request->post('type', 'local');
        $category = $request->post('category', '');
        $password = $request->post('password', '');
        $initRepo = $request->post('init_repo', '0') === '1';
        $backupPathsRaw = $request->post('backup_paths', '');
        $s3Key = trim($request->post('s3_key', ''));
        $s3Secret = trim($request->post('s3_secret', ''));
        $s3Endpoint = trim($request->post('s3_endpoint', ''));

        $locationField = $this->locationFieldFor($type);
        $locationValue = trim($request->post($locationField, ''));

        if ($name === '' || $locationValue === '') {
            App::session()->flash('error', __('repo.name_path_required'));
            App::response()->redirect('/repositories/add');
            return;
        }

        if (!in_array($category, ['public', 'private', 'session'], true)) {
            App::session()->flash('error', __('repo.invalid_category'));
            App::response()->redirect('/repositories/add');
            return;
        }

        if (!$auth->canEdit($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $settings = App::configStorage()->loadSettings();

        $location = RepositoryPath::normalize($type, $locationValue, $settings['repo_base_dir'] ?? null);

        if ($type === 'local' && !RepositoryPath::localRepoAllowed($location, $settings['repo_paths_roots'] ?? [])) {
            App::session()->flash('error', __('repo.path_outside_roots', ['{roots}' => implode(', ', $settings['repo_paths_roots'] ?? [])]));
            App::response()->redirect('/repositories/add');
            return;
        }

        $backupPaths = array_values(array_filter(
            array_map('trim', explode("\n", $backupPathsRaw)),
            function (string $p): bool { return $p !== ''; }
        ));

        $disallowedBackup = RepositoryPath::firstDisallowedBackupPath($backupPaths, $settings['backup_paths_roots'] ?? []);
        if ($disallowedBackup !== null) {
            App::session()->flash('error', __('repo.backup_path_outside_roots', ['{roots}' => implode(', ', $settings['backup_paths_roots'] ?? [])]));
            App::response()->redirect('/repositories/add');
            return;
        }

        $repository = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'type' => $type,
            'password' => $password !== '' ? $password : null,
        ];
        $repository[$locationField] = $location;

        if (!empty($backupPaths)) {
            $repository['backup_paths'] = $backupPaths;
        }

        if ($s3Key !== '' || $s3Secret !== '' || $s3Endpoint !== '') {
            $repository['env'] = [];
            if ($s3Key !== '') {
                $repository['env']['AWS_ACCESS_KEY_ID'] = $s3Key;
            }
            if ($s3Secret !== '') {
                $repository['env']['AWS_SECRET_ACCESS_KEY'] = $s3Secret;
            }
            if ($s3Endpoint !== '') {
                $repository['env']['AWS_ENDPOINT'] = $s3Endpoint;
            }
        }

        if ($initRepo) {
            if (!$auth->canInit()) {
                App::response()->error(403, __('error.forbidden'));
                return;
            }

            $result = App::repoService()->init($repository);
            if (!$result['ok']) {
                App::session()->flash('error', __('flash.init_failed', ['{error}' => $result['error']]));
                App::response()->redirect('/repositories/add');
                return;
            }
        }

        App::repoStorage()->save($category, $repository, $user);
        App::session()->flash('success', __('flash.repo_added'));
        App::response()->redirect('/repositories');
    }

    /**
     * POST /repositories/delete — удаление репозитория.
     */
    public function delete(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => __('flash.csrf_error'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $repoId = $request->post('repo_id', '');

        if ($repoId === '') {
            App::response()->json(['ok' => false, 'error' => 'Repository ID is required', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $found = null;

        foreach ($repositories as $repo) {
            if (($repo['id'] ?? '') === $repoId) {
                $found = $repo;
                break;
            }
        }

        if ($found === null) {
            App::response()->json(['ok' => false, 'error' => __('flash.not_found'), '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $category = $found['category'] ?? 'public';

        if (!$auth->canDelete()) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        App::repoStorage()->delete($category, $repoId, $user);

        // Сбросить current_repo если удалённый id совпадает
        if (App::session()->get('current_repo') === $repoId) {
            App::session()->remove('current_repo');
        }

        App::session()->flash('success', __('flash.repo_deleted'));
        App::response()->json(['ok' => true, 'redirect' => '/repositories', '_csrf_token' => App::security()->csrfToken()]);
    }

    /**
     * POST /repositories/move — перенос между категориями.
     */
    public function move(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => __('flash.csrf_error'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $repoId = $request->post('repo_id', '');
        $toCategory = $request->post('to_category', '');

        if ($repoId === '' || $toCategory === '') {
            App::response()->json(['ok' => false, 'error' => 'Repository ID and target category are required', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        if (!in_array($toCategory, ['public', 'private', 'session'], true)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid category', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $found = null;

        foreach ($repositories as $repo) {
            if (($repo['id'] ?? '') === $repoId) {
                $found = $repo;
                break;
            }
        }

        if ($found === null) {
            App::response()->json(['ok' => false, 'error' => __('flash.not_found'), '_csrf_token' => App::security()->csrfToken()], 404);
            return;
        }

        $fromCategory = $found['category'] ?? 'public';

        if ($fromCategory === $toCategory) {
            App::response()->json(['ok' => false, 'error' => 'Repository is already in this category', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        if (!$auth->canMove($fromCategory, $toCategory)) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        App::repoStorage()->move($repoId, $fromCategory, $toCategory, $user);

        $fromLabel = __('repo.category.' . $fromCategory);
        $toLabel = __('repo.category.' . $toCategory);
        App::session()->flash('success', __('flash.repo_moved', ['{from}' => $fromLabel, '{to}' => $toLabel]));

        App::response()->json(['ok' => true, 'redirect' => '/repositories', '_csrf_token' => App::security()->csrfToken()]);
    }

    private function locationFieldFor(string $type): string
    {
        return match ($type) {
            's3' => 's3_bucket',
            'sftp' => 'sftp_path',
            'rest' => 'rest_url',
            default => 'local_path',
        };
    }
}
