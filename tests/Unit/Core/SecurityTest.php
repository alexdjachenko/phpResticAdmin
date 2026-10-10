<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Core;

use App\Core\Security;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Security (CSRF-токены и экранирование HTML).
 *
 * Цель: проверить генерацию, валидацию и одноразовость CSRF-токенов,
 *       а также корректность htmlspecialchars-экранирования.
 *
 * Сценарий:
 *   1. csrfToken(): генерация возвращает непустую строку, повторный вызов — тот же токен.
 *   2. validateCsrf(): валидный токен → true, невалидный → false.
 *   3. validateCsrf() без предварительной генерации → false.
 *   4. Токен потребляется после первой проверки (одноразовость).
 *   5. h(): экранирование HTML-сущностей.
 *
 * Критерий успеха: все проверки проходят.
 */
class SecurityTest extends TestCase
{
    private Session $session;
    private Security $security;

    protected function setUp(): void
    {
        // Запускаем сессию вручную (без веб-сервера)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $this->session = new Session();
        $this->session->start();
        $this->security = new Security($this->session);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Токен генерируется и повторный вызов возвращает тот же токен. */
    public function testCsrfTokenGeneratesAndReturnsSameToken(): void
    {
        $token1 = $this->security->csrfToken();
        $token2 = $this->security->csrfToken();

        // Токен не пустой и повторный вызов возвращает тот же
        $this->assertNotEmpty($token1);
        $this->assertSame($token1, $token2);
    }

    /** Валидация с правильным токеном возвращает true. */
    public function testValidateCsrfReturnsTrueForValidToken(): void
    {
        $token = $this->security->csrfToken();
        $this->assertTrue($this->security->validateCsrf($token));
    }

    /** Валидация с неправильным токеном возвращает false. */
    public function testValidateCsrfReturnsFalseForInvalidToken(): void
    {
        // Генерируем токен, но проверяем другой
        $this->security->csrfToken();
        $this->assertFalse($this->security->validateCsrf('invalid'));
    }

    /** Валидация без предварительной генерации токена возвращает false. */
    public function testValidateCsrfReturnsFalseWhenNoTokenGenerated(): void
    {
        $this->assertFalse($this->security->validateCsrf('anything'));
    }

    /** Токен потребляется (удаляется) после первой успешной проверки. */
    public function testValidateCsrfConsumesTokenOnFirstValidation(): void
    {
        $token = $this->security->csrfToken();

        // Первая проверка — успешна
        $this->assertTrue($this->security->validateCsrf($token));
        // Вторая проверка с тем же токеном — неуспешна (токен уже удалён)
        $this->assertFalse($this->security->validateCsrf($token));
    }

    /** Экранирование HTML: <, >, &, " */
    public function testHEscapesHtml(): void
    {
        $this->assertSame('&lt;script&gt;', $this->security->h('<script>'));
        $this->assertSame('foo &amp; bar', $this->security->h('foo & bar'));
        $this->assertSame('&quot;quoted&quot;', $this->security->h('"quoted"'));
    }
}
