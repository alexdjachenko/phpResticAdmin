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

class FormController extends BaseController
{
    /**
     * GET /repositories/add — форма добавления.
     */
    public function addForm(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $auth = App::auth();
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

        $this->render('repositories/add.php', [
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'csrfToken' => App::security()->csrfToken(),
            'categories' => $availableCategories,
            'canInit' => $auth->canInit(),
            'error' => $flash,
        ]);
    }

    /**
     * POST /repositories/add — сохранение + опционально restic init.
     */
    public function add(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/repositories/add')) {
            return;
        }

        $request = $this->request();
        $auth = App::auth();

        $name = trim((string) $request->post('name', ''));
        $type = (string) $request->post('type', 'local');
        $category = (string) $request->post('category', '');
        $password = (string) $request->post('password', '');
        $initRepo = $request->post('init_repo', '0') === '1';
        $backupPathsRaw = (string) $request->post('backup_paths', '');
        $s3Key = trim((string) $request->post('s3_key', ''));
        $s3Secret = trim((string) $request->post('s3_secret', ''));
        $s3Endpoint = trim((string) $request->post('s3_endpoint', ''));

        $locationField = $this->locationFieldFor($type);
        $locationValue = trim((string) $request->post($locationField, ''));

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
     * GET /repositories/edit — форма редактирования.
     */
    public function editForm(): void
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

        $repo = $this->findRepo($user, $repoId);
        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';

        if (!App::auth()->canEdit($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $this->render('repositories/edit.php', [
            'repo' => $repo,
            'category' => $category,
            'error' => App::session()->flash('error'),
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /repositories/edit — сохранение изменений.
     */
    public function edit(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(false, '/repositories')) {
            return;
        }

        $request = $this->request();
        $auth = App::auth();

        $repoId = (string) $request->post('repo_id', '');
        $name = trim((string) $request->post('name', ''));
        $type = (string) $request->post('type', 'local');
        $password = (string) $request->post('password', '');
        $backupPathsRaw = (string) $request->post('backup_paths', '');
        $s3Key = trim((string) $request->post('s3_key', ''));
        $s3Secret = trim((string) $request->post('s3_secret', ''));
        $s3Endpoint = trim((string) $request->post('s3_endpoint', ''));

        $locationField = $this->locationFieldFor($type);
        $locationValue = trim((string) $request->post($locationField, ''));

        if ($name === '' || $locationValue === '') {
            App::session()->flash('error', __('repo.name_path_required'));
            App::response()->redirect('/repositories/edit?repo=' . urlencode($repoId));
            return;
        }

        $found = $this->findRepo($user, $repoId);
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
