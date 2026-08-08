<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

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

        $repositories = App::repoStorage()->loadAll($user);
        $flash = App::session()->flash('success');
        $csrfToken = App::security()->csrfToken();

        $availableCategories = [];
        foreach (['public', 'private', 'session'] as $cat) {
            if ($auth->canEdit($cat)) {
                $availableCategories[$cat] = __('repo.category.' . $cat);
            }
        }

        $canAdd = !empty($availableCategories);
        $canDeleteGlobal = $auth->canDelete();

        foreach ($repositories as &$repo) {
            $cat = $repo['category'] ?? 'public';
            $repo['canDelete'] = $canDeleteGlobal;
            $repo['canMove'] = $auth->canEdit($cat);
        }
        unset($repo);

        echo App::response()->render('repositories/list.php', [
            'repositories' => $repositories,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'flash' => $flash,
            'csrfToken' => $csrfToken,
            'canAdd' => $canAdd,
            'availableCategories' => $availableCategories,
            'categories' => $availableCategories,
            'debug' => App::isDebug(),
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
        $path = trim($request->post('path', ''));
        $category = $request->post('category', '');
        $password = $request->post('password', '');
        $initRepo = $request->post('init_repo', '0') === '1';
        $s3Key = trim($request->post('s3_key', ''));
        $s3Secret = trim($request->post('s3_secret', ''));

        if ($name === '' || $path === '') {
            App::session()->flash('error', 'Name and path are required.');
            App::response()->redirect('/repositories/add');
            return;
        }

        if (!in_array($category, ['public', 'private', 'session'], true)) {
            App::session()->flash('error', 'Invalid category.');
            App::response()->redirect('/repositories/add');
            return;
        }

        if (!$auth->canEdit($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $path = $this->normalizePath($path);

        $repository = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'type' => $type,
            'path' => $path,
            'password' => $password !== '' ? $password : null,
        ];

        if ($s3Key !== '' || $s3Secret !== '') {
            $repository['env'] = [];
            if ($s3Key !== '') {
                $repository['env']['AWS_ACCESS_KEY_ID'] = $s3Key;
            }
            if ($s3Secret !== '') {
                $repository['env']['AWS_SECRET_ACCESS_KEY'] = $s3Secret;
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

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/') || str_contains($path, '://')) {
            return $path;
        }

        $settings = App::configStorage()->loadSettings();
        $baseDir = rtrim($settings['repo_base_dir'] ?? '/backups', '/');

        return $baseDir . '/' . $path;
    }
}
