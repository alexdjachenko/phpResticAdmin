<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Core;

use App\Auth\Authenticator;
use App\Restic\CommandRunner;
use App\Restic\KeyService;
use App\Restic\MaintenanceService;
use App\Restic\RepositoryService;
use App\Restic\SnapshotService;
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
    private static ?SnapshotService $snapshotService = null;
    private static ?MaintenanceService $maintenanceService = null;
    private static ?KeyService $keyService = null;
    private static ?Security $security = null;
    private static ?Response $response = null;

    private static int $debugLevel = 0;
    private static ?string $resticVersion = null;

    public static function boot(): void
    {
        $settings = self::configStorage()->loadSettings();

        date_default_timezone_set($settings['timezone'] ?? 'UTC');
        self::$debugLevel = (int) ($settings['debug'] ?? 0);

        self::session()->start();
        self::auth()->resolve();

        $currentRepoId = self::session()->get('current_repo');
        if ($currentRepoId !== null) {
            $username = self::auth()->user();
            $repos = self::repoStorage()->loadAll($username ?? '');
            $stillExists = false;
            foreach ($repos as $r) {
                if (($r['id'] ?? '') === $currentRepoId) {
                    $stillExists = true;
                    break;
                }
            }
            if (!$stillExists) {
                self::session()->remove('current_repo');
            }
        }

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

    public static function debugLevel(): int
    {
        return self::$debugLevel;
    }

    public static function isDebug(): bool
    {
        return self::$debugLevel >= 1;
    }

    public static function log(string $message, int $level = 1): void
    {
        if ($level <= self::$debugLevel) {
            error_log('[phpresticadmin] ' . $message);
        }
    }

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

    public static function appVersion(): string
    {
        $file = dirname(__DIR__, 2) . '/version.txt';
        if (file_exists($file)) {
            return trim(file_get_contents($file));
        }
        return 'dev';
    }

    public static function resticVersion(): string
    {
        if (self::$resticVersion === null) {
            $result = self::runner()->run(['restic', 'version']);
            if ($result['exitCode'] === 0 && preg_match('/restic (\S+)/', $result['stdout'], $m)) {
                self::$resticVersion = $m[1];
            } else {
                self::$resticVersion = 'unknown';
            }
        }
        return self::$resticVersion;
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

    public static function snapshotService(): SnapshotService
    {
        if (self::$snapshotService === null) {
            self::$snapshotService = new SnapshotService(self::runner());
        }
        return self::$snapshotService;
    }

    public static function maintenanceService(): MaintenanceService
    {
        if (self::$maintenanceService === null) {
            self::$maintenanceService = new MaintenanceService(self::runner());
        }
        return self::$maintenanceService;
    }

    public static function keyService(): KeyService
    {
        if (self::$keyService === null) {
            self::$keyService = new KeyService(self::runner());
        }
        return self::$keyService;
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

        $router->map('GET', '/repositories/detail', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->detail();
        });

        $router->map('GET', '/repositories/edit', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->editForm();
        });

        $router->map('POST', '/repositories/edit', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->edit();
        });

        $router->map('POST', '/repositories/backup', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->backup();
        });

        $router->map('POST', '/repositories/select', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->select();
        });

        $router->map('GET', '/snapshots', function () {
            $controller = new \App\Controllers\SnapshotController();
            $controller->list();
        });

        $router->map('GET', '/snapshots/detail', function () {
            $controller = new \App\Controllers\SnapshotController();
            $controller->detail();
        });

        $router->map('POST', '/snapshots/tag', function () {
            $controller = new \App\Controllers\SnapshotController();
            $controller->tag();
        });

        $router->map('POST', '/snapshots/copy', function () {
            $controller = new \App\Controllers\SnapshotController();
            $controller->copy();
        });

        $router->map('POST', '/snapshots/stats', function () {
            $controller = new \App\Controllers\SnapshotController();
            $controller->stats();
        });

        $router->map('GET', '/browse', function () {
            $controller = new \App\Controllers\BrowseController();
            $controller->tree();
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

        $router->map('GET', '/download', function () {
            $controller = new \App\Controllers\ExportController();
            $controller->file();
        });

        $router->map('GET', '/export', function () {
            $controller = new \App\Controllers\ExportController();
            $controller->snapshot();
        });

        $router->map('GET', '/maintenance', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->index();
        });

        $router->map('POST', '/maintenance/init', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->init();
        });

        $router->map('POST', '/maintenance/check', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->check();
        });

        $router->map('POST', '/maintenance/prune', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->prune();
        });

        $router->map('POST', '/maintenance/rebuild-index', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->rebuildIndex();
        });

        $router->map('POST', '/maintenance/unlock', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->unlock();
        });

        $router->map('POST', '/maintenance/forget', function () {
            $controller = new \App\Controllers\MaintenanceController();
            $controller->forget();
        });

        $router->map('GET', '/keys', function () {
            $controller = new \App\Controllers\KeyController();
            $controller->list();
        });

        $router->map('POST', '/keys/verify', function () {
            $controller = new \App\Controllers\KeyController();
            $controller->verify();
        });

        $router->map('POST', '/keys/add', function () {
            $controller = new \App\Controllers\KeyController();
            $controller->add();
        });

        $router->map('POST', '/keys/remove', function () {
            $controller = new \App\Controllers\KeyController();
            $controller->remove();
        });

        $router->map('POST', '/keys/passwd', function () {
            $controller = new \App\Controllers\KeyController();
            $controller->passwd();
        });
    }
}
