<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Request (обёртка над $_SERVER/$_GET/$_POST).
 *
 * Цель: проверить чтение заголовков и определение AJAX-запроса, от которого
 *       зависит выбор JSON вместо редиректа.
 *
 * Сценарий:
 *   1. header() читает HTTP_-переменную (регистронезависимо от имени).
 *   2. isAjax() = true для X-Requested-With: XMLHttpRequest.
 *   3. isAjax() = true для Accept: application/json.
 *   4. isAjax() = false для обычного запроса.
 *
 * Критерий успеха: все assert проходят.
 */
class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testHeaderIsReadCaseInsensitively(): void
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'value';
        $request = new Request();

        $this->assertSame('value', $request->header('X-Custom-Header'));
        $this->assertSame('value', $request->header('x-custom-header'));
    }

    public function testIsAjaxForXmlHttpRequest(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue((new Request())->isAjax());
    }

    public function testIsAjaxForJsonAccept(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain';
        $this->assertTrue((new Request())->isAjax());
    }

    public function testIsNotAjaxForPlainRequest(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);
        $this->assertFalse((new Request())->isAjax());
    }
}
