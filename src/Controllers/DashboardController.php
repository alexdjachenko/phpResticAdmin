<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class DashboardController
{
    public function index(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        // Сбрасываем current_repo при заходе на дашборд,
        // чтобы не показывать данные из предыдущей сессии в новой вкладке.
        App::session()->remove('current_repo');

        $repoCount = 0;
        if ($user !== null) {
            $repoCount = count(App::repoStorage()->loadAll($user));
        }

        echo App::response()->render('dashboard.php', [
            'repoCount' => $repoCount,
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    public function invalidateCache(): void
    {
        $auth = App::auth();
        if (!$auth->isLoggedIn()) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required'], 403);
            return;
        }

        if (!App::isDebug()) {
            App::response()->json(['ok' => false, 'error' => 'Debug mode is disabled'], 403);
            return;
        }

        $request = new Request();
        $security = App::security();

        $token = $request->post('_csrf_token', '');
        if (!$security->validateCsrf($token)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid security token', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $result = App::invalidateCaches();
        $result['ok'] = true;
        $result['_csrf_token'] = App::security()->csrfToken();

        App::log('Cache invalidated: ' . $result['count'] . ' scripts cleared', 0);

        App::response()->json($result);
    }
}
