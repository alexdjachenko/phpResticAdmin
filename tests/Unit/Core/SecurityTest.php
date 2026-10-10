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
 * Цель: проверить генерацию, валидацию и переиспользуемость CSRF-токенов
 *       (session-bound), ротацию и корректность htmlspecialchars-экранирования.
 *
 * Сценарий:
 *   1. csrfToken(): генерация возвращает непустую строку, повторный вызов — тот же токен.
 *   2. validateCsrf(): валидный токен → true, невалидный → false.
 *   3. validateCsrf() без предварительной генерации → false.
 *   4. Токен НЕ гасится при проверке — один и тот же токен валиден многократно.
 *   5. rotate() выпускает новый токен (старый перестаёт быть валидным).
 *   6. h(): экранирование HTML-сущностей.
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

    /**
     * Токен переиспользуемый: один и тот же токен проходит проверку многократно.
     *
     * Это ключевое свойство session-bound токена: несколько вкладок и носителей
     * делят один токен, и успешный POST не ломает остальные.
     */
    public function testValidateCsrfTokenIsReusableWithinSession(): void
    {
        $token = $this->security->csrfToken();

        $this->assertTrue($this->security->validateCsrf($token), 'first validation must pass');
        $this->assertTrue($this->security->validateCsrf($token), 'same token must stay valid (session-bound)');
    }

    /** rotate() выпускает новый токен, старый становится невалидным. */
    public function testRotateIssuesNewToken(): void
    {
        $old = $this->security->csrfToken();

        $this->security->rotate();
        $new = $this->security->csrfToken();

        $this->assertNotSame($old, $new, 'rotate must issue a different token');
        $this->assertFalse($this->security->validateCsrf($old), 'old token must not validate after rotation');
        $this->assertTrue($this->security->validateCsrf($new));
    }

    /** Экранирование HTML: <, >, &, " */
    public function testHEscapesHtml(): void
    {
        $this->assertSame('&lt;script&gt;', $this->security->h('<script>'));
        $this->assertSame('foo &amp; bar', $this->security->h('foo & bar'));
        $this->assertSame('&quot;quoted&quot;', $this->security->h('"quoted"'));
    }
}
