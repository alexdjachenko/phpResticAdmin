<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Таблица роутов приложения.
 *
 * Единственное место, описывающее соответствие «метод + путь → контроллер +
 * действие». App::registerRoutes() разворачивает её в Router циклом, поэтому
 * REST-эндпоинты добавляются сюда без правок ядра.
 */
final class Routes
{
    /**
     * @return array<int, array{0: string, 1: string, 2: class-string, 3: string}>
     */
    public static function all(): array
    {
        return [
            ['GET', '/', \App\Controllers\Dashboard\DashboardController::class, 'index'],

            ['GET', '/login', \App\Controllers\Auth\AuthController::class, 'loginForm'],
            ['POST', '/login', \App\Controllers\Auth\AuthController::class, 'login'],
            ['GET', '/logout', \App\Controllers\Auth\AuthController::class, 'logout'],

            ['POST', '/language', \App\Controllers\Language\LanguageController::class, 'switch'],

            ['GET', '/repositories', \App\Controllers\Repository\ListController::class, 'list'],
            ['GET', '/repositories/add', \App\Controllers\Repository\FormController::class, 'addForm'],
            ['POST', '/repositories/add', \App\Controllers\Repository\FormController::class, 'add'],
            ['GET', '/repositories/detail', \App\Controllers\Repository\DetailController::class, 'detail'],
            ['GET', '/repositories/edit', \App\Controllers\Repository\FormController::class, 'editForm'],
            ['POST', '/repositories/edit', \App\Controllers\Repository\FormController::class, 'edit'],
            ['POST', '/repositories/check', \App\Controllers\Repository\ActionController::class, 'check'],
            ['POST', '/repositories/delete', \App\Controllers\Repository\ActionController::class, 'delete'],
            ['POST', '/repositories/move', \App\Controllers\Repository\ActionController::class, 'move'],
            ['POST', '/repositories/backup', \App\Controllers\Repository\ActionController::class, 'backup'],
            ['POST', '/repositories/select', \App\Controllers\Repository\ActionController::class, 'select'],

            ['GET', '/snapshots', \App\Controllers\Snapshot\ListController::class, 'list'],
            ['GET', '/snapshots/detail', \App\Controllers\Snapshot\DetailController::class, 'detail'],
            ['GET', '/snapshots/stats/result', \App\Controllers\Snapshot\StatsController::class, 'statsResult'],
            ['POST', '/snapshots/stats', \App\Controllers\Snapshot\StatsController::class, 'stats'],
            ['POST', '/snapshots/tag', \App\Controllers\Snapshot\ActionController::class, 'tag'],
            ['POST', '/snapshots/copy', \App\Controllers\Snapshot\ActionController::class, 'copy'],
            ['POST', '/snapshots/refresh', \App\Controllers\Snapshot\ActionController::class, 'refresh'],

            ['GET', '/browse', \App\Controllers\Browse\BrowseController::class, 'tree'],

            ['POST', '/cache/invalidate', \App\Controllers\Dashboard\DashboardController::class, 'invalidateCache'],

            ['GET', '/download', \App\Controllers\Export\ExportController::class, 'file'],
            ['GET', '/export', \App\Controllers\Export\ExportController::class, 'snapshot'],

            ['GET', '/maintenance', \App\Controllers\Maintenance\IndexController::class, 'index'],
            ['POST', '/maintenance/connection', \App\Controllers\Maintenance\IndexController::class, 'connection'],
            ['POST', '/maintenance/init', \App\Controllers\Maintenance\ActionController::class, 'init'],
            ['POST', '/maintenance/check', \App\Controllers\Maintenance\ActionController::class, 'check'],
            ['POST', '/maintenance/prune', \App\Controllers\Maintenance\ActionController::class, 'prune'],
            ['POST', '/maintenance/rebuild-index', \App\Controllers\Maintenance\ActionController::class, 'rebuildIndex'],
            ['POST', '/maintenance/unlock', \App\Controllers\Maintenance\ActionController::class, 'unlock'],
            ['POST', '/maintenance/forget', \App\Controllers\Maintenance\ActionController::class, 'forget'],
            ['POST', '/maintenance/stats', \App\Controllers\Maintenance\ActionController::class, 'stats'],

            ['GET', '/keys', \App\Controllers\Key\KeyController::class, 'list'],
            ['POST', '/keys/verify', \App\Controllers\Key\KeyController::class, 'verify'],
            ['POST', '/keys/add', \App\Controllers\Key\KeyController::class, 'add'],
            ['POST', '/keys/remove', \App\Controllers\Key\KeyController::class, 'remove'],
            ['POST', '/keys/passwd', \App\Controllers\Key\KeyController::class, 'passwd'],

            ['GET', '/tasks', \App\Controllers\Task\TaskController::class, 'list'],
            ['GET', '/tasks/view', \App\Controllers\Task\TaskController::class, 'view'],
            ['GET', '/tasks/stream', \App\Controllers\Task\TaskController::class, 'stream'],
            ['GET', '/tasks/status', \App\Controllers\Task\TaskController::class, 'status'],
            ['GET', '/tasks/active', \App\Controllers\Task\TaskController::class, 'active'],
            ['POST', '/tasks/cancel', \App\Controllers\Task\TaskController::class, 'cancel'],
            ['POST', '/tasks/promote', \App\Controllers\Task\TaskController::class, 'promote'],

            ['GET', '/users', \App\Controllers\User\UserController::class, 'list'],
            ['GET', '/users/add', \App\Controllers\User\UserController::class, 'addForm'],
            ['POST', '/users/add', \App\Controllers\User\UserController::class, 'add'],
            ['GET', '/users/edit', \App\Controllers\User\UserController::class, 'editForm'],
            ['POST', '/users/edit', \App\Controllers\User\UserController::class, 'edit'],
            ['POST', '/users/delete', \App\Controllers\User\UserController::class, 'delete'],

            ['GET', '/account/password', \App\Controllers\Account\AccountController::class, 'passwordForm'],
            ['POST', '/account/password', \App\Controllers\Account\AccountController::class, 'changePassword'],
        ];
    }
}
