<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Cache\CacheManager;
use App\Storage\ContentCacheStorage;
use App\Storage\ContentTargets;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест ContentCacheStorage (запись кеша с обещанием).
 *
 * Цель: проверить выбор области кеша по категории объекта и жизненный цикл
 *       записи: put/promise/fail/read/clear + сохранение просроченной записи.
 *
 * Сценарий:
 *   - публичный репо пишет в системную область, приватный — в пользовательскую;
 *     статистика — всегда системная (проверяем по ключам сессии);
 *   - put → read().state() = ready, promise снят;
 *   - promise → read().promise = label, value = null;
 *   - fail → read().error = сообщение;
 *   - просроченная запись не удаляется, read().stale = true;
 *   - clear убирает запись.
 *
 * Критерий успеха: все assert проходят.
 */
class ContentCacheStorageTest extends TestCase
{
    private CacheManager $cache;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->cache = new CacheManager('session');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    private function storage(int $ttl = 600): ContentCacheStorage
    {
        return new ContentCacheStorage($this->cache, $ttl, 31536000);
    }

    /** Публичный репо → системная область; приватный → пользовательская. */
    public function testScopeIsChosenByRepoCategory(): void
    {
        $storage = $this->storage();

        $storage->put(ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']), [['id' => 'a']]);
        $storage->put(ContentTargets::forSnapshots(['id' => 'priv1', 'category' => 'private']), [['id' => 'b']]);

        $this->assertArrayHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION, 'public repo must use the system area');
        $this->assertArrayHasKey('cache_user_repos/priv1/snapshots.json', $_SESSION, 'private repo must use the user area');
        $this->assertArrayNotHasKey('cache_user_repos/pub1/snapshots.json', $_SESSION);
        $this->assertArrayNotHasKey('cache_system_repos/priv1/snapshots.json', $_SESSION);
    }

    /** Статистика пишется в системную область (id снепшота глобально уникален). */
    public function testStatsUseSystemArea(): void
    {
        $storage = $this->storage();
        $storage->put(ContentTargets::forStats('r1', 'snap123'), ['total_size' => 10]);

        $this->assertArrayHasKey('cache_system_snapshots/snap123/stats.json', $_SESSION);

        $entry = $storage->read(ContentTargets::forStats('r1', 'snap123'));
        $this->assertNotNull($entry);
        $this->assertSame(['total_size' => 10], $entry->value);
    }

    /** put → read отдаёт fresh-значение, обещание снято. */
    public function testPutAndReadBack(): void
    {
        $storage = $this->storage();
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $storage->put($target, [['id' => 'abc']], ['duration' => 3]);

        $entry = $storage->read($target);
        $this->assertNotNull($entry);
        $this->assertSame([['id' => 'abc']], $entry->value);
        $this->assertNull($entry->promise);
        $this->assertNull($entry->error);
        $this->assertFalse($entry->stale);
        $this->assertSame('ready', $entry->state());
        $this->assertSame([['id' => 'abc']], $storage->value($target));
    }

    /** promise хранит метку без значения. */
    public function testPromiseStoresLabelWithoutValue(): void
    {
        $storage = $this->storage();
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $storage->promise($target, 'alice#snapshotspub10123456789abcdef');

        $entry = $storage->read($target);
        $this->assertNotNull($entry);
        $this->assertSame('alice#snapshotspub10123456789abcdef', $entry->promise);
        $this->assertNull($entry->value);
        $this->assertNull($storage->value($target));
    }

    /** fail хранит ошибку. */
    public function testFailStoresError(): void
    {
        $storage = $this->storage();
        $target = ContentTargets::forSnapshots(['id' => 'priv1', 'category' => 'private']);

        $storage->fail($target, 'boom', 'label');

        $entry = $storage->read($target);
        $this->assertNotNull($entry);
        $this->assertSame('boom', $entry->error);
        $this->assertNull($entry->value);
        $this->assertSame('failed', $entry->state());
    }

    /** Просроченная запись не удаляется: stale = true, value() → null. */
    public function testStaleEntryIsNotDeleted(): void
    {
        $_SESSION['cache_system_repos/pub1/snapshots.json'] = [
            'cached_at' => time() - 1000,
            'value' => ['value' => [['id' => 'x']], 'promise' => null, 'error' => null, 'meta' => null],
        ];

        $storage = $this->storage(10);
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $this->assertNull($storage->value($target));

        $entry = $storage->read($target);
        $this->assertNotNull($entry);
        $this->assertTrue($entry->stale);
        $this->assertSame([['id' => 'x']], $entry->value);
        $this->assertSame('absent', $entry->state());
        $this->assertArrayHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION, 'stale entry must not be deleted');
    }

    /** clear убирает запись. */
    public function testClear(): void
    {
        $storage = $this->storage();
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $storage->put($target, [['id' => 'abc']]);
        $storage->clear($target);

        $this->assertNull($storage->read($target));
        $this->assertArrayNotHasKey('cache_system_repos/pub1/snapshots.json', $_SESSION);
    }
}
