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

    public static function boot(): void
    {
        date_default_timezone_set(self::configStorage()->loadSettings()['timezone'] ?? 'UTC');

        self::session()->start();
        self::auth()->resolve();

        self::registerRoutes();
    }

    public static function run(): void
    {
        $request = new Request();
        self::router()->dispatch($request);
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

        $router->map('POST', '/repositories/check', function () {
            $controller = new \App\Controllers\RepositoryController();
            $controller->check();
        });
    }
}
