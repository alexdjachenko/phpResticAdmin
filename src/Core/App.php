<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Core;

use App\Auth\Authenticator;
use App\Cache\CacheManager;
use App\Process\TspClient;
use App\Process\TspCommandRunner;
use App\Process\TspTaskManager;
use App\Restic\CommandRunner;
use App\Restic\KeyService;
use App\Restic\MaintenanceService;
use App\Restic\RepositoryService;
use App\Restic\ResticTaskService;
use App\Restic\SnapshotService;
use App\Storage\ConfigStorage;
use App\Storage\RepositoryStorage;
use App\Storage\SnapshotCacheStorage;
use App\Storage\SnapshotListState;

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
    private static ?TspClient $tsp = null;
    private static ?TspTaskManager $tasks = null;
    private static ?TspCommandRunner $tspRunner = null;
    private static ?ResticTaskService $resticTasks = null;
    private static ?Security $security = null;
    private static ?Response $response = null;
    private static ?CacheManager $cache = null;
    private static ?SnapshotCacheStorage $snapshotCache = null;

    private static int $debugLevel = 0;

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

    /**
     * Версия restic.
     *
     * Мемоизация на запрос (область Request) поверх кеша в сессии (область User):
     * один запуск `restic version` за запрос, результат переиспользуется между
     * запросами в рамках сессии.
     */
    public static function resticVersion(): string
    {
        $version = self::cache()->request()->remember('restic_version', null, function (): string {
            return self::cache()->user()->remember('restic_version', null, function (): string {
                $result = self::runner()->run(['restic', 'version']);
                if ($result['exitCode'] === 0 && preg_match('/restic (\S+)/', $result['stdout'], $m)) {
                    return $m[1];
                }
                return 'unknown';
            });
        });

        return is_string($version) ? $version : 'unknown';
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
            self::$keyService = new KeyService(self::runner(), self::cache()->request());
        }
        return self::$keyService;
    }

    public static function tsp(): TspClient
    {
        if (self::$tsp === null) {
            self::$tsp = new TspClient(self::runner());
        }
        return self::$tsp;
    }

    public static function tasks(): TspTaskManager
    {
        if (self::$tasks === null) {
            self::$tasks = new TspTaskManager(self::tsp());
        }
        return self::$tasks;
    }

    public static function tspRunner(): TspCommandRunner
    {
        if (self::$tspRunner === null) {
            self::$tspRunner = new TspCommandRunner(self::tasks(), self::tsp(), self::runner());
        }
        return self::$tspRunner;
    }

    public static function resticTasks(): ResticTaskService
    {
        if (self::$resticTasks === null) {
            self::$resticTasks = new ResticTaskService(self::tasks());
        }
        return self::$resticTasks;
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

    public static function cache(): CacheManager
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager();
        }
        return self::$cache;
    }

    /**
     * Доменная обёртка кеша производных от restic данных (список/статистика).
     */
    public static function snapshotCache(): SnapshotCacheStorage
    {
        if (self::$snapshotCache === null) {
            $settings = self::configStorage()->loadSettings();
            self::$snapshotCache = new SnapshotCacheStorage(
                self::cache(),
                isset($settings['snapshot_cache_ttl']) ? (int) $settings['snapshot_cache_ttl'] : null,
                isset($settings['snapshot_stats_cache_ttl']) ? (int) $settings['snapshot_stats_cache_ttl'] : null
            );
        }
        return self::$snapshotCache;
    }

    public static function snapshotListState(): SnapshotListState
    {
        return new SnapshotListState(self::snapshotCache(), self::tasks());
    }

    /**
     * Сбрасывает область текущего запроса.
     *
     * Используется тестами вместо ручного обнуления статики отдельных классов.
     * Системную область не трогает: иначе кнопка «инвалидация кеша» вызывала бы
     * лавину обращений к restic.
     */
    public static function resetCaches(): void
    {
        if (self::$cache !== null) {
            self::$cache->request()->clear();
        }
    }

    /**
     * Регистрирует роуты из таблицы App\Core\Routes::all() циклом.
     *
     * Единственное место, знающее о перечне эндпоинтов; REST-роуты добавляются
     * в таблицу без правок ядра.
     */
    private static function registerRoutes(): void
    {
        $router = self::router();

        foreach (Routes::all() as [$method, $path, $controller, $action]) {
            $router->map($method, $path, function () use ($controller, $action): void {
                $instance = new $controller();
                $instance->{$action}();
            });
        }
    }
}
