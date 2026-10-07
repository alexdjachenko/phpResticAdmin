<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Controllers\Account\AccountController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Browse\BrowseController;
use App\Controllers\Dashboard\DashboardController;
use App\Controllers\Export\ExportController;
use App\Controllers\Key\KeyController;
use App\Controllers\Language\LanguageController;
use App\Controllers\Maintenance\ActionController as MaintenanceActionController;
use App\Controllers\Maintenance\IndexController as MaintenanceIndexController;
use App\Controllers\Repository\ActionController as RepositoryActionController;
use App\Controllers\Repository\DetailController as RepositoryDetailController;
use App\Controllers\Repository\FormController as RepositoryFormController;
use App\Controllers\Repository\ListController as RepositoryListController;
use App\Controllers\Snapshot\ActionController as SnapshotActionController;
use App\Controllers\Snapshot\DetailController as SnapshotDetailController;
use App\Controllers\Snapshot\ListController as SnapshotListController;
use App\Controllers\Snapshot\StatsController as SnapshotStatsController;
use App\Controllers\Task\TaskController;
use App\Controllers\User\UserController;
use App\Core\Routes;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест таблицы роутов App\Core\Routes.
 *
 * Цель: таблица роутов — единственный источник истины о соответствии
 *       «метод + путь → контроллер + действие». Проверить, что она совпадает
 *       с таблицей роутов из AGENTS.md, не содержит дублей и что у каждого
 *       контроллера есть заявленное действие.
 *
 * Сценарий:
 *   1. В Routes::all() нет двух одинаковых пар (method, path).
 *   2. Все задокументированные пары присутствуют с правильным контроллером и
 *      действием; незадокументированных пар нет.
 *   3. Для каждой пары method_exists(Controller::class, action) === true.
 *
 * Критерий успеха: все assert проходят.
 */
class RoutesTest extends TestCase
{
    /**
     * Каноническая таблица роутов (совпадает с таблицей в AGENTS.md).
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    private static function expected(): array
    {
        return [
            'GET /' => [DashboardController::class, 'index'],

            'GET /login' => [AuthController::class, 'loginForm'],
            'POST /login' => [AuthController::class, 'login'],
            'GET /logout' => [AuthController::class, 'logout'],

            'POST /language' => [LanguageController::class, 'switch'],

            'GET /repositories' => [RepositoryListController::class, 'list'],
            'GET /repositories/add' => [RepositoryFormController::class, 'addForm'],
            'POST /repositories/add' => [RepositoryFormController::class, 'add'],
            'GET /repositories/detail' => [RepositoryDetailController::class, 'detail'],
            'GET /repositories/edit' => [RepositoryFormController::class, 'editForm'],
            'POST /repositories/edit' => [RepositoryFormController::class, 'edit'],
            'POST /repositories/check' => [RepositoryActionController::class, 'check'],
            'POST /repositories/delete' => [RepositoryActionController::class, 'delete'],
            'POST /repositories/move' => [RepositoryActionController::class, 'move'],
            'POST /repositories/backup' => [RepositoryActionController::class, 'backup'],
            'POST /repositories/select' => [RepositoryActionController::class, 'select'],

            'GET /snapshots' => [SnapshotListController::class, 'list'],
            'GET /snapshots/detail' => [SnapshotDetailController::class, 'detail'],
            'GET /snapshots/stats/result' => [SnapshotStatsController::class, 'statsResult'],
            'POST /snapshots/stats' => [SnapshotStatsController::class, 'stats'],
            'POST /snapshots/tag' => [SnapshotActionController::class, 'tag'],
            'POST /snapshots/copy' => [SnapshotActionController::class, 'copy'],
            'POST /snapshots/refresh' => [SnapshotActionController::class, 'refresh'],

            'GET /browse' => [BrowseController::class, 'tree'],

            'POST /cache/invalidate' => [DashboardController::class, 'invalidateCache'],

            'GET /download' => [ExportController::class, 'file'],
            'GET /export' => [ExportController::class, 'snapshot'],

            'GET /maintenance' => [MaintenanceIndexController::class, 'index'],
            'POST /maintenance/connection' => [MaintenanceIndexController::class, 'connection'],
            'POST /maintenance/init' => [MaintenanceActionController::class, 'init'],
            'POST /maintenance/check' => [MaintenanceActionController::class, 'check'],
            'POST /maintenance/prune' => [MaintenanceActionController::class, 'prune'],
            'POST /maintenance/rebuild-index' => [MaintenanceActionController::class, 'rebuildIndex'],
            'POST /maintenance/unlock' => [MaintenanceActionController::class, 'unlock'],
            'POST /maintenance/forget' => [MaintenanceActionController::class, 'forget'],
            'POST /maintenance/stats' => [MaintenanceActionController::class, 'stats'],

            'GET /keys' => [KeyController::class, 'list'],
            'POST /keys/verify' => [KeyController::class, 'verify'],
            'POST /keys/add' => [KeyController::class, 'add'],
            'POST /keys/remove' => [KeyController::class, 'remove'],
            'POST /keys/passwd' => [KeyController::class, 'passwd'],

            'GET /tasks' => [TaskController::class, 'list'],
            'GET /tasks/view' => [TaskController::class, 'view'],
            'GET /tasks/stream' => [TaskController::class, 'stream'],
            'GET /tasks/status' => [TaskController::class, 'status'],
            'GET /tasks/active' => [TaskController::class, 'active'],
            'POST /tasks/cancel' => [TaskController::class, 'cancel'],
            'POST /tasks/promote' => [TaskController::class, 'promote'],

            'GET /users' => [UserController::class, 'list'],
            'GET /users/add' => [UserController::class, 'addForm'],
            'POST /users/add' => [UserController::class, 'add'],
            'GET /users/edit' => [UserController::class, 'editForm'],
            'POST /users/edit' => [UserController::class, 'edit'],
            'POST /users/delete' => [UserController::class, 'delete'],

            'GET /account/password' => [AccountController::class, 'passwordForm'],
            'POST /account/password' => [AccountController::class, 'changePassword'],
        ];
    }

    /**
     * В таблице нет двух одинаковых пар (method, path).
     */
    public function testNoDuplicateMethodPathPairs(): void
    {
        // Arrange
        $seen = [];

        // Act + Assert
        foreach (Routes::all() as [$method, $path]) {
            $key = $method . ' ' . $path;
            $this->assertArrayNotHasKey($key, $seen, 'Duplicate route: ' . $key);
            $seen[$key] = true;
        }
    }

    /**
     * Таблица совпадает с задокументированными роутами (AGENTS.md).
     */
    public function testTableMatchesDocumentedRoutes(): void
    {
        // Arrange
        $actual = [];
        foreach (Routes::all() as [$method, $path, $controller, $action]) {
            $actual[$method . ' ' . $path] = [$controller, $action];
        }
        $expected = self::expected();

        // Assert: все задокументированные роуты присутствуют и с верным таргетом
        foreach ($expected as $key => [$controller, $action]) {
            $this->assertArrayHasKey($key, $actual, 'Missing documented route: ' . $key);
            $this->assertSame($controller, $actual[$key][0], 'Wrong controller for ' . $key);
            $this->assertSame($action, $actual[$key][1], 'Wrong action for ' . $key);
        }

        // Assert: нет незадокументированных роутов
        foreach ($actual as $key => $pair) {
            $this->assertArrayHasKey($key, $expected, 'Undocumented route: ' . $key);
        }
    }

    /**
     * У каждого контроллера есть заявленное действие.
     */
    public function testControllerActionsExist(): void
    {
        foreach (Routes::all() as [$method, $path, $controller, $action]) {
            $this->assertTrue(
                method_exists($controller, $action),
                $controller . '::' . $action . '() не существует (' . $method . ' ' . $path . ')'
            );
        }
    }
}
