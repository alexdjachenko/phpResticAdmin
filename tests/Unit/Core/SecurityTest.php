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

class SecurityTest extends TestCase
{
    private Session $session;
    private Security $security;

    protected function setUp(): void
    {
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

    public function testCsrfTokenGeneratesAndReturnsSameToken(): void
    {
        $token1 = $this->security->csrfToken();
        $token2 = $this->security->csrfToken();

        $this->assertNotEmpty($token1);
        $this->assertSame($token1, $token2);
    }

    public function testValidateCsrfReturnsTrueForValidToken(): void
    {
        $token = $this->security->csrfToken();
        $this->assertTrue($this->security->validateCsrf($token));
    }

    public function testValidateCsrfReturnsFalseForInvalidToken(): void
    {
        $this->security->csrfToken();
        $this->assertFalse($this->security->validateCsrf('invalid'));
    }

    public function testValidateCsrfReturnsFalseWhenNoTokenGenerated(): void
    {
        $this->assertFalse($this->security->validateCsrf('anything'));
    }

    public function testValidateCsrfConsumesTokenOnFirstValidation(): void
    {
        $token = $this->security->csrfToken();
        $this->assertTrue($this->security->validateCsrf($token));
        $this->assertFalse($this->security->validateCsrf($token));
    }

    public function testHEscapesHtml(): void
    {
        $this->assertSame('&lt;script&gt;', $this->security->h('<script>'));
        $this->assertSame('foo &amp; bar', $this->security->h('foo & bar'));
        $this->assertSame('&quot;quoted&quot;', $this->security->h('"quoted"'));
    }
}
