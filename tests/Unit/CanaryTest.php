<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Дымовой (canary) юнит-тест.
 *
 * Цель: убедиться, что PHPUnit работает, версия PHP >= 8.1,
 *       и основной класс приложения автозагружается.
 *
 * Сценарий:
 *   1. Проверка assertTrue(true) — базовый canary.
 *   2. Проверка версии PHP.
 *   3. Проверка существования класса App\Core\App.
 *
 * Критерий успеха: все три assert проходят.
 */
class CanaryTest extends TestCase
{
    /** Базовый canary: PHPUnit запущен и выполняет тесты. */
    public function testPhpunitWorks(): void
    {
        $this->assertTrue(true, 'PHPUnit canary: tests are running');
    }

    /** Приложение требует PHP 8.1+. */
    public function testPhpVersionIsAtLeast81(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.1.0', '>='),
            'PHP version must be >= 8.1, got ' . PHP_VERSION
        );
    }

    /** Главный класс приложения должен быть доступен через автозагрузку. */
    public function testAppClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Core\App::class), 'App\Core\App class must be autoloadable');
    }
}
