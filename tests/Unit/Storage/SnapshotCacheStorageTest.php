<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Core\Session;
use App\Storage\SnapshotCacheStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест SnapshotCacheStorage (кеш списка снепшотов в сессии).
 *
 * Цель: проверить set/get/invalidate, TTL и жизненный цикл метки задачи.
 *
 * Сценарий:
 *   - set → get возвращает данные.
 *   - invalidate → get возвращает null.
 *   - устаревшая запись (cached_at в прошлом) → get возвращает null.
 *   - taskLabel lifecycle: setTaskLabel → taskLabel → clearTaskLabel → null.
 *
 * Критерий успеха: все assert проходят.
 */
class SnapshotCacheStorageTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $this->session = new Session();
        $this->session->start();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** set → get возвращает сохранённые данные. */
    public function testSetAndGet(): void
    {
        $storage = new SnapshotCacheStorage($this->session, 600);
        $storage->set('repo1', [['id' => 'abc']]);

        $this->assertSame([['id' => 'abc']], $storage->get('repo1'));
    }

    /** invalidate → get возвращает null. */
    public function testInvalidate(): void
    {
        $storage = new SnapshotCacheStorage($this->session, 600);
        $storage->set('repo1', [['id' => 'abc']]);
        $storage->invalidate('repo1');

        $this->assertNull($storage->get('repo1'));
    }

    /** Устаревшая запись возвращает null и удаляется. */
    public function testExpiredEntryReturnsNull(): void
    {
        $_SESSION['snapshot_cache_repo1'] = [
            'cached_at' => time() - 100,
            'snapshots' => [['id' => 'old']],
        ];

        $storage = new SnapshotCacheStorage($this->session, 10);
        $this->assertNull($storage->get('repo1'));
        $this->assertArrayNotHasKey('snapshot_cache_repo1', $_SESSION);
    }

    /** Свежая запись возвращает данные. */
    public function testFreshEntryReturnsData(): void
    {
        $_SESSION['snapshot_cache_repo2'] = [
            'cached_at' => time(),
            'snapshots' => [['id' => 'fresh']],
        ];

        $storage = new SnapshotCacheStorage($this->session, 10);
        $this->assertSame([['id' => 'fresh']], $storage->get('repo2'));
    }

    /** taskLabel lifecycle. */
    public function testTaskLabelLifecycle(): void
    {
        $storage = new SnapshotCacheStorage($this->session, 600);

        $this->assertNull($storage->taskLabel('repo1'));

        $storage->setTaskLabel('repo1', 'alice#abc123');
        $this->assertSame('alice#abc123', $storage->taskLabel('repo1'));

        $storage->clearTaskLabel('repo1');
        $this->assertNull($storage->taskLabel('repo1'));
    }
}
