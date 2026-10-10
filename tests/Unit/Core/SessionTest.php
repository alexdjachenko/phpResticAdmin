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
 *   6. start() настраивает cookie сессии (SameSite=Lax, HttpOnly; Secure по HTTPS).
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

    /** start() выставляет SameSite=Lax и HttpOnly для cookie сессии. */
    public function testStartSetsCookieAttributes(): void
    {
        // Гарантируем, что сессия не активна: иначе start() не применит параметры cookie.
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        $_SESSION = [];

        $session = new Session();
        $session->start();

        $params = session_get_cookie_params();
        $this->assertSame('Lax', $params['samesite'], 'session cookie must be SameSite=Lax');
        $this->assertTrue($params['httponly'], 'session cookie must be HttpOnly');

        $session->close();
    }

    /** Secure-флаг cookie зависит от того, идёт ли запрос по HTTPS. */
    public function testSecureFlagFollowsHttps(): void
    {
        $backup = $_SERVER;

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        // HTTPS — cookie должна быть Secure.
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        (new Session())->start();
        $this->assertTrue(session_get_cookie_params()['secure'], 'secure cookie expected under HTTPS');
        (new Session())->close();

        // Обычный HTTP — cookie не Secure (иначе браузер её не пришлёт).
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = '80';
        (new Session())->start();
        $this->assertFalse(session_get_cookie_params()['secure'], 'non-secure cookie expected over plain HTTP');
        (new Session())->close();

        $_SERVER = $backup;
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
