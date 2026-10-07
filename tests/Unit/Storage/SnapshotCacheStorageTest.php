<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Cache\CacheManager;
use App\Storage\SnapshotCacheStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест SnapshotCacheStorage (доменная обёртка над кеш-слоем).
 *
 * Цель: проверить выбор области кеша по категории репозитория и жизненный
 *       цикл записей списка/статистики.
 *
 * Сценарий:
 *   - публичный репозиторий пишет в системную область, приватный/сессионный —
 *     в пользовательскую (проверяем по ключам сессии: cache_system_* / cache_user_*);
 *   - set → entry/list возвращают данные; markTask хранит метку без данных;
 *   - setListError хранит ошибку;
 *   - просроченная запись не удаляется, list() возвращает null, entry.stale = true;
 *   - invalidateList убирает запись;
 *   - статистика снепшота (глобальная) пишется в системную область.
 *
 * Критерий успеха: все assert проходят.
 */
class SnapshotCacheStorageTest extends TestCase
{
    private CacheManager $cache;
    /** @var array<string, mixed> */
    private array $repoPublic;
    /** @var array<string, mixed> */
    private array $repoPrivate;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->cache = new CacheManager('session');
        $this->repoPublic = ['id' => 'pub1', 'category' => 'public'];
        $this->repoPrivate = ['id' => 'priv1', 'category' => 'private'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    /** Публичный репозиторий → системная область; приватный → пользовательская. */
    public function testScopeIsChosenByRepoCategory(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);

        $storage->setList($this->repoPublic, [['id' => 'a']]);
        $storage->setList($this->repoPrivate, [['id' => 'b']]);

        $this->assertArrayHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION, 'public repo must use the system area');
        $this->assertArrayHasKey('cache_user_repos/priv1/snapshots.json', $_SESSION, 'private repo must use the user area');
        $this->assertArrayNotHasKey('cache_user_repos/pub1/snapshots.json', $_SESSION);
        $this->assertArrayNotHasKey('cache_system_repos/priv1/snapshots.json', $_SESSION);
    }

    /** set → list возвращает данные, entry содержит время и признак свежести. */
    public function testSetListAndReadBack(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);
        $storage->setList($this->repoPublic, [['id' => 'abc']], 'alice#snapshotsr10123456789abcdef');

        $this->assertSame([['id' => 'abc']], $storage->list($this->repoPublic));

        $entry = $storage->listEntry($this->repoPublic);
        $this->assertNotNull($entry);
        $this->assertSame([['id' => 'abc']], $entry['snapshots']);
        $this->assertSame('alice#snapshotsr10123456789abcdef', $entry['task_label']);
        $this->assertNull($entry['error']);
        $this->assertFalse($entry['stale']);
    }

    /** markTask хранит метку задачи без данных списка. */
    public function testMarkTaskStoresLabelWithoutSnapshots(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);
        $storage->markTask($this->repoPublic, 'alice#snapshotsr10123456789abcdef');

        $entry = $storage->listEntry($this->repoPublic);
        $this->assertNotNull($entry);
        $this->assertSame('alice#snapshotsr10123456789abcdef', $entry['task_label']);
        $this->assertNull($entry['snapshots']);
        $this->assertNull($storage->list($this->repoPublic));
    }

    /** setListError хранит ошибку. */
    public function testSetListError(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);
        $storage->setListError($this->repoPrivate, 'boom', 'label');

        $entry = $storage->listEntry($this->repoPrivate);
        $this->assertNotNull($entry);
        $this->assertSame('boom', $entry['error']);
        $this->assertNull($entry['snapshots']);
    }

    /** Просроченная запись не удаляется: entry.stale = true, list → null. */
    public function testStaleEntryIsNotDeleted(): void
    {
        $_SESSION['cache_system_repos/pub1/snapshots.json'] = [
            'cached_at' => time() - 1000,
            'value' => ['task_label' => null, 'error' => null, 'snapshots' => [['id' => 'x']], 'started_at' => null, 'duration' => null],
        ];

        $storage = new SnapshotCacheStorage($this->cache, 10, 31536000);

        $this->assertNull($storage->list($this->repoPublic));

        $entry = $storage->listEntry($this->repoPublic);
        $this->assertNotNull($entry);
        $this->assertTrue($entry['stale']);
        $this->assertSame([['id' => 'x']], $entry['snapshots']);
        $this->assertArrayHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION, 'stale entry must not be deleted');
    }

    /** invalidateList убирает запись. */
    public function testInvalidateList(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);
        $storage->setList($this->repoPublic, [['id' => 'abc']]);
        $storage->invalidateList($this->repoPublic);

        $this->assertNull($storage->listEntry($this->repoPublic));
        $this->assertArrayNotHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION);
    }

    /** Статистика снепшота пишется в системную область (id глобально уникален). */
    public function testStatsUseSystemArea(): void
    {
        $storage = new SnapshotCacheStorage($this->cache, 600, 31536000);
        $storage->setStats('snap123', ['total_size' => 10]);

        $this->assertArrayHasKey('cache_system_snapshots/snap123/stats.json', $_SESSION);
        $this->assertSame(['total_size' => 10], $storage->stats('snap123'));

        $entry = $storage->statsEntry('snap123');
        $this->assertNotNull($entry);
        $this->assertFalse($entry['stale']);
    }
}
