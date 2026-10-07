<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Cache;

use App\Cache\CacheManager;
use App\Cache\CacheScope;
use App\Cache\RequestCache;
use App\Cache\SessionCache;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест менеджера областей кеша.
 *
 * Цель: проверить выбор драйвера по контексту (for()), единственность
 *       инстансов областей и безопасную деградацию без сессии.
 *
 * Сценарий:
 *   1. for(Request) → RequestCache, for(User) → SessionCache.
 *   2. for(System) при неизвестном драйвере → сессионная область (fallback).
 *   3. user()/request() возвращают один и тот же инстанс.
 *   4. user() не падает без активной сессии.
 *
 * Критерий успеха: все assert проходят.
 */
class CacheManagerTest extends TestCase
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

    /** for() отдаёт драйвер нужного контекста. */
    public function testForReturnsDriverByScope(): void
    {
        $manager = new CacheManager('session');

        $this->assertInstanceOf(RequestCache::class, $manager->for(CacheScope::Request));
        $this->assertInstanceOf(SessionCache::class, $manager->for(CacheScope::User));
        $this->assertInstanceOf(SessionCache::class, $manager->for(CacheScope::System));
    }

    /** for(System) при неизвестном драйвере деградирует до сессии (не падает). */
    public function testUnknownDriverFallsBackToSession(): void
    {
        $manager = new CacheManager('redis');

        $system = $manager->for(CacheScope::System);
        $this->assertInstanceOf(SessionCache::class, $system);

        $system->set('repos/x/snapshots.json', ['ok' => true]);
        $this->assertSame(['ok' => true], $system->get('repos/x/snapshots.json'));
    }

    /** request()/user() возвращают один и тот же инстанс на менеджер. */
    public function testInstancesAreReused(): void
    {
        $manager = new CacheManager('session');

        $this->assertSame($manager->request(), $manager->request());
        $this->assertSame($manager->user(), $manager->user());
    }

    /** user() не падает без активной сессии. */
    public function testUserDoesNotThrowWithoutSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        $manager = new CacheManager('session');
        $user = $manager->user();

        $user->set('k', 'v');
        $this->assertNull($user->get('k'));
    }
}
