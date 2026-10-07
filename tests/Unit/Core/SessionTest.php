<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Core;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Session (обёртка над PHP-сессиями).
 *
 * Цель: проверить базовые операции (set/get, remove, flash, destroy) и
 *       освобождение сессии через close() с возможностью переоткрытия.
 *
 * Сценарий:
 *   1. set/get и default для отсутствующего ключа.
 *   2. remove удаляет ключ.
 *   3. flash: запись, самоуничтожение после чтения, отсутствующий ключ.
 *   4. destroy очищает все данные.
 *   5. close(): сессия перестаёт быть активной, запись после close не
 *      сохраняется, повторный start() открывает сессию снова.
 *
 * Критерий успеха: все assert проходят.
 */
class SessionTest extends TestCase
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

    /** Базовый set → get. */
    public function testSetAndGet(): void
    {
        $session = new Session();
        $session->start();

        $session->set('test_key', 'test_value');
        $this->assertSame('test_value', $session->get('test_key'));
    }

    /** get для отсутствующего ключа возвращает default (по умолчанию null). */
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $session = new Session();
        $session->start();

        $this->assertNull($session->get('nonexistent'));
        $this->assertSame('default', $session->get('nonexistent', 'default'));
    }

    /** remove удаляет ключ. */
    public function testRemove(): void
    {
        $session = new Session();
        $session->start();

        $session->set('key', 'value');
        $session->remove('key');
        $this->assertNull($session->get('key'));
    }

    /** flash: запись и немедленное чтение — возвращает значение. */
    public function testFlashSetThenGet(): void
    {
        $session = new Session();
        $session->start();

        $session->flash('success', 'Operation completed');
        $this->assertSame('Operation completed', $session->flash('success'));
    }

    /** flash самоуничтожается после первого чтения. */
    public function testFlashSelfDestructsAfterRead(): void
    {
        $session = new Session();
        $session->start();

        $session->flash('info', 'Message');

        $session->flash('info');
        $this->assertNull($session->flash('info'));
    }

    /** flash для несуществующего ключа возвращает null. */
    public function testFlashReturnsNullForMissingKey(): void
    {
        $session = new Session();
        $session->start();

        $this->assertNull($session->flash('nonexistent'));
    }

    /** destroy очищает все данные сессии. */
    public function testDestroyClearsAllData(): void
    {
        $session = new Session();
        $session->start();

        $session->set('key1', 'val1');
        $session->set('key2', 'val2');
        $session->destroy();

        $this->assertNull($session->get('key1'));
        $this->assertNull($session->get('key2'));
    }

    /**
     * close() освобождает сессию: запись после close не сохраняется,
     * а повторный start() снова открывает сессию.
     */
    public function testCloseStopsPersistingAndCanReopen(): void
    {
        $session = new Session();
        $session->start();
        $session->set('before', 'kept');

        $session->close();
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status(), 'session must be closed for writing');

        // Запись после close() не должна сохраняться.
        $session->set('after', 'lost');

        $session->start();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'start() must reopen the session');

        $this->assertSame('kept', $session->get('before'));
        $this->assertNull($session->get('after'), 'value written after close() must not persist');
    }
}
