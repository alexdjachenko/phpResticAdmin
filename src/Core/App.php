<?php

namespace App\Core;

use App\Auth\Authenticator;
use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use App\Storage\ConfigStorage;
use App\Storage\RepositoryStorage;

class App
{
    private static ?ConfigStorage $configStorage = null;
    private static ?Session $session = null;
    private static ?Authenticator $auth = null;
    private static ?Router $router = null;
    private static ?RepositoryStorage $repoStorage = null;
    private static ?CommandRunner $runner = null;
    private static ?RepositoryService $repoService = null;
    private static ?Security $security = null;
    private static ?Response $response = null;

    /**
     * Уровень отладки: 0 = выкл, 1 = info, 2 = verbose.
     */
    private static int $debugLevel = 0;

    public static function boot(): void
    {
        $settings = self::configStorage()->loadSettings();

        date_default_timezone_set($settings['timezone'] ?? 'UTC');
        self::$debugLevel = (int) ($settings['debug'] ?? 0);

        self::session()->start();
        self::auth()->resolve();

        // Инициализация языка
        $userLang = self::session()->get('lang');
        if ($userLang !== null) {
            \App\Helpers\Lang::setLocale($userLang);
        } else {
            $detected = \App\Helpers\Lang::detectFromRequest();
            \App\Helpers\Lang::setLocale($detected);
            self::session()->set('lang', $detected);
        }

        self::registerRoutes();
    }

    public static function run(): void
    {
        $request = new Request();
        self::router()->dispatch($request);
    }

    /**
     * Уровень отладки из настроек.
     */
    public static function debugLevel(): int
    {
        return self::$debugLevel;
    }

    /**
     * Включён ли режим отладки (уровень >= 1).
     */
    public static function isDebug(): bool
    {
        return self::$debugLevel >= 1;
    }

    /**
     * Запись в лог. Уровни: 0 = всегда, 1 = info, 2 = verbose.
     * Сообщение пишется только если $level <= debugLevel.
     */
    public static function log(string $message, int $level = 1): void
    {
        if ($level <= self::$debugLevel) {
            error_log('[phpresticadmin] ' . $message);
        }
    }

    /**
     * Инвалидация кешей (opcache).
     * @return array{count: int, files: array<int, string>}
     */
    public static function invalidateCaches(): array
    {
        $count = 0;
        $files = [];

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            $scripts = $status['scripts'] ?? [];
            $count = count($scripts);
            if ($count > 0 && self::$debugLevel >= 2) {
                foreach ($scripts as $path => $info) {
                    $files[] = str_replace('/var/www/', '', $path);
                }
            }
        }

        return ['count' => $count, 'files' => $files];
    }

    public static function configStorage(): ConfigStorage
    {
        if (self::$configStorage === null) {
            self::$configStorage = new ConfigStorage();
        }
        return self::$configStorage;
    }

    public static function session(): Session
    {
        if (self::$session === null) {
            self::$session = new Session();
        }
        return self::$session;
    }

    public static function auth(): Authenticator
    {
        if (self::$auth === null) {
            self::$auth = new Authenticator(self::configStorage(), self::session());
        }
        return self::$auth;
    }

    public static function router(): Router
    {
        if (self::$router === null) {
            self::$router = new Router();
        }
        return self::$router;
    }

    public static function repoStorage(): RepositoryStorage
    {
        if (self::$repoStorage === null) {
            self::$repoStorage = new RepositoryStorage();
        }
        return self::$repoStorage;
    }

    public static function runner(): CommandRunner
    {
        if (self::$runner === null) {
            self::$runner = new CommandRunner();
        }
        return self::$runner;
    }

    public static function repoService(): RepositoryService
    {
        if (self::$repoService === null) {
            self::$repoService = new RepositoryService(self::runner());
        }
        return self::$repoService;
    }

    public static function security(): Security
    {
        if (self::$security === null) {
            self::$security = new Security(self::session());
        }
        return self::$security;
    }

    public static function response(): Response
    {
        if (self::$response === null) {
            self::$response = new Response();
        }
        return self::$response;
    }

    private static function registerRoutes(): void
    {
        $router = self::router();

        $router->map('GET', '/', function () {
            $controller = new \App\Controllers\DashboardController();
            $controller->index();
        });

        $router->map('GET', '/login', function () {
            $controller = new \App\Controllers\AuthController();
            $controller->loginForm();
        });

        $router->map('POST', '/login', function () {
            $controller = new \App\Controllers\AuthController();
            $controller->login();
        });

        $router->map('GET', '/logout', function () {
            $controller = new \App\Controllers\AuthController();
            $controller->logout();
        });

        $router->map('GET', '/repositories', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->list();
        });

        $router->map('GET', '/repositories/add', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->addForm();
        });

        $router->map('POST', '/repositories/add', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->add();
        });

        $router->map('POST', '/repositories/check', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->check();
        });

        $router->map('POST', '/repositories/delete', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->delete();
        });

        $router->map('POST', '/repositories/move', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->move();
        });

        $router->map('POST', '/language', function () {
            $request = new \App\Core\Request();
            $lang = $request->post('lang', 'en');
            if (in_array($lang, \App\Helpers\Lang::available(), true)) {
                \App\Core\App::session()->set('lang', $lang);
                \App\Helpers\Lang::setLocale($lang);
            }
            \App\Core\App::response()->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        });

        $router->map('POST', '/cache/invalidate', function () {
            $controller = new \App\Controllers\DashboardController();
            $controller->invalidateCache();
        });
    }
}
