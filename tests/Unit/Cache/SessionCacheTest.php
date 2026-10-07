<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Cache;

use App\Cache\SessionCache;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест сессионного драйвера кеша.
 *
 * Цель: проверить запись/чтение через сессию, TTL, разведение неймспейсов и
 *       деградацию при неактивной сессии (CLI/REST): get() → null, set() без
 *       падения.
 *
 * Сценарий:
 *   1. set → get возвращает значение (сессия активна).
 *   2. Просроченная запись: get → null, stale = true.
 *   3. Разные неймспейсы не пересекаются.
 *   4. Сессия не активна: get → null, set не падает, entry → null.
 *
 * Критерий успеха: все assert проходят.
 */
class SessionCacheTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    public function testSetAndGet(): void
    {
        $cache = new SessionCache('user', new Session());
        $cache->set('restic_version', '0.19.1');

        $this->assertSame('0.19.1', $cache->get('restic_version'));
    }

    public function testStaleEntry(): void
    {
        $cache = new SessionCache('user', new Session());
        $cache->set('k', 'v');

        $entry = $cache->entry('k', 10);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['stale']);

        $session = new Session();
        $session->set('cache_user_k', ['cached_at' => time() - 100, 'value' => 'v']);

        $entry = $cache->entry('k', 10);
        $this->assertNotNull($entry);
        $this->assertTrue($entry['stale']);
        $this->assertNull($cache->get('k', 10));
    }

    /** Разные неймспейсы не пересекаются: 'user' и 'system' независимы. */
    public function testNamespacesAreIsolated(): void
    {
        $session = new Session();
        $user = new SessionCache('user', $session);
        $system = new SessionCache('system', $session);

        $user->set('k', 'user-value');
        $system->set('k', 'system-value');

        $this->assertSame('user-value', $user->get('k'));
        $this->assertSame('system-value', $system->get('k'));
    }

    public function testDegradesWhenSessionInactive(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        $cache = new SessionCache('user', new Session());

        $cache->set('k', 'v');
        $this->assertNull($cache->get('k'), 'set must be a no-op without an active session');
        $this->assertNull($cache->entry('k'));
    }
}
