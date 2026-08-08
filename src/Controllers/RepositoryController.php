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

        $repositories = App::repoStorage()->loadAll();
        $flash = App::session()->flash('success');
        $csrfToken = App::security()->csrfToken();

        echo App::response()->render('repositories/list.php', [
            'repositories' => $repositories,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
            'flash' => $flash,
            'csrfToken' => $csrfToken,
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

        $repositories = App::repoStorage()->loadAll();
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
}
