<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Cache\CacheManager;
use App\Process\TspTaskManager;
use App\Storage\SnapshotCacheStorage;
use App\Storage\SnapshotListState;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест автомата состояния списка снепшотов.
 *
 * Цель: проверить все ветви решения (list/progress/error/start) и, главное,
 *       отсутствие цикла перезапусков: при зафиксированной ошибке повторный
 *       вызов даёт ошибку, а не старт; авто-старт возможен только когда нет
 *       метки либо задача пропала из очереди.
 *
 * Сценарий: TspTaskManager замокан; SnapshotCacheStorage — на реальной сессионной
 * области (CacheManager('session')). Для «просроченных» кейсов listTtl = -1.
 *
 * Критерий успеха: все assert проходят.
 */
class SnapshotListStateTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $repo;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->repo = ['id' => 'pub1', 'category' => 'public'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    private function storage(int $ttl): SnapshotCacheStorage
    {
        return new SnapshotCacheStorage(new CacheManager('session'), $ttl, 31536000);
    }

    /** Свежая запись → list, задача не запрашивается. */
    public function testFreshEntryYieldsList(): void
    {
        $storage = $this->storage(600);
        $storage->setList($this->repo, [['id' => 'a']]);

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->expects($this->never())->method('findByLabel');

        $result = (new SnapshotListState($storage, $tasks))->resolve($this->repo, 'alice', false);

        $this->assertSame('list', $result['state']);
        $this->assertSame([['id' => 'a']], $result['snapshots']);
    }

    /** Просроченная запись + активная задача → progress, новая задача не ставится. */
    public function testStaleEntryWithActiveTaskYieldsProgress(): void
    {
        $storage = $this->storage(-1);
        $storage->markTask($this->repo, 'alice#snapshotsr10123456789abcdef');

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('isValidLabel')->willReturn(true);
        $tasks->method('findByLabel')->willReturn(['id' => 5, 'state' => 'running', 'label' => 'alice#snapshotsr10123456789abcdef']);

        $result = (new SnapshotListState($storage, $tasks))->resolve($this->repo, 'alice', false);

        $this->assertSame('progress', $result['state']);
        $this->assertSame('running', $result['task_state']);
    }

    /** Просроченная запись + задача finished/exit 0 → list, запись перезаписана. */
    public function testFinishedSuccessYieldsList(): void
    {
        $storage = $this->storage(-1);
        $storage->markTask($this->repo, 'alice#snapshotsr10123456789abcdef');

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('isValidLabel')->willReturn(true);
        $tasks->method('findByLabel')->willReturn(['id' => 5, 'state' => 'finished', 'label' => 'alice#snapshotsr10123456789abcdef']);
        $tasks->method('catResult')->willReturn(['exitCode' => 0, 'output' => '[{"id":"a"},{"id":"b"}]']);

        $result = (new SnapshotListState($storage, $tasks))->resolve($this->repo, 'alice', false);

        $this->assertSame('list', $result['state']);
        $this->assertCount(2, $result['snapshots']);

        // Запись перезаписана данными, метка задачи снята.
        $entry = $storage->listEntry($this->repo);
        $this->assertNotNull($entry);
        $this->assertNull($entry['task_label']);
        $this->assertCount(2, $entry['snapshots']);
    }

    /**
     * Просроченная запись + задача finished/exit≠0 → error; повторный вызов
     * даёт снова error, а не start (регрессия на бесконечный перезапуск).
     */
    public function testFinishedFailureYieldsStickyError(): void
    {
        $storage = $this->storage(-1);
        $storage->markTask($this->repo, 'alice#snapshotsr10123456789abcdef');

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('isValidLabel')->willReturn(true);
        $tasks->method('findByLabel')->willReturn(['id' => 5, 'state' => 'finished', 'label' => 'alice#snapshotsr10123456789abcdef']);
        $tasks->method('catResult')->willReturn(['exitCode' => 1, 'output' => 'boom']);

        $state = new SnapshotListState($storage, $tasks);

        $first = $state->resolve($this->repo, 'alice', false);
        $this->assertSame('error', $first['state']);

        $second = $state->resolve($this->repo, 'alice', false);
        $this->assertSame('error', $second['state'], 'error must be sticky, not restarted');
    }

    /** Задача пропала из очереди → start. */
    public function testTaskGoneYieldsStart(): void
    {
        $storage = $this->storage(-1);
        $storage->markTask($this->repo, 'alice#snapshotsr10123456789abcdef');

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('isValidLabel')->willReturn(true);
        $tasks->method('findByLabel')->willReturn(null);

        $result = (new SnapshotListState($storage, $tasks))->resolve($this->repo, 'alice', false);

        $this->assertSame('start', $result['state']);
    }

    /** Метки нет → start. */
    public function testNoLabelYieldsStart(): void
    {
        $storage = $this->storage(600);
        $tasks = $this->createMock(TspTaskManager::class);

        $result = (new SnapshotListState($storage, $tasks))->resolve($this->repo, 'alice', false);

        $this->assertSame('start', $result['state']);
    }
}
