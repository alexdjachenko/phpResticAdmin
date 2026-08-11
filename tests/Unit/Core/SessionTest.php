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
 * Цель: проверить базовые операции: set/get, remove, flash-сообщения
 *       (включая самоуничтожение после чтения), destroy.
 *
 * Сценарий:
 *   1. set/get: запись и чтение значения по ключу.
 *   2. get с отсутствующим ключом: возврат default/null.
 *   3. remove: удаление ключа.
 *   4. flash: запись, чтение (самоуничтожение), повторное чтение (null).
 *   5. flash для отсутствующего ключа: null.
 *   6. destroy: полная очистка всех данных.
 *
 * Критерий успеха: все assert проходят.
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Инициализируем сессию вручную (без веб-сервера)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
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

        // Первое чтение — сообщение доступно
        $session->flash('info');
        // Второе чтение — сообщение уже удалено
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
}
