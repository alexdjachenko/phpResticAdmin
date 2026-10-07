<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Cache;

use App\Cache\RequestCache;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест области текущего запроса (в памяти).
 *
 * Цель: проверить мемоизацию remember() и полный сброс через clear().
 *
 * Сценарий:
 *   1. remember вызывает producer ровно один раз при повторных вызовах.
 *   2. set/get и remove.
 *   3. clear() возвращает область к исходному (пустому) состоянию.
 *
 * Критерий успеха: все assert проходят.
 */
class RequestCacheTest extends TestCase
{
    public function testRememberCallsProducerOnce(): void
    {
        $cache = new RequestCache();
        $calls = 0;
        $producer = function () use (&$calls) {
            $calls++;
            return ['list'];
        };

        $this->assertSame(['list'], $cache->remember('tsp.list', null, $producer));
        $this->assertSame(['list'], $cache->remember('tsp.list', null, $producer));
        $this->assertSame(1, $calls);
    }

    public function testSetGetRemove(): void
    {
        $cache = new RequestCache();
        $cache->set('k', 'v');
        $this->assertSame('v', $cache->get('k'));

        $cache->remove('k');
        $this->assertNull($cache->get('k'));
    }

    public function testClearResetsStore(): void
    {
        $cache = new RequestCache();
        $cache->set('a', 1);
        $cache->set('b', 2);

        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));

        $calls = 0;
        $cache->remember('c', null, function () use (&$calls) {
            $calls++;
            return 3;
        });
        $cache->remember('c', null, function () use (&$calls) {
            $calls++;
            return 3;
        });
        $this->assertSame(1, $calls);
    }
}
