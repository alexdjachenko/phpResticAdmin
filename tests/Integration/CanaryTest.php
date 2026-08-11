<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Дымовой (smoke) интеграционный тест.
 *
 * Цель: убедиться, что веб-сервер запущен, отвечает на HTTP-запросы,
 *       и в ответе присутствует идентифицирующая строка приложения.
 *
 * Сценарий:
 *   1. Делается HTTP GET на базовый URL приложения (из переменной окружения
 *      TEST_BASE_URL или localhost:8080 по умолчанию).
 *   2. Проверяется, что ответ не пустой и содержит "phpResticAdmin".
 *
 * Критерий успеха:
 *   - HTTP-ответ получен (не false).
 *   - В теле ответа есть строка "phpResticAdmin".
 *
 * Требует: запущенный веб-сервер приложения (в CI — через Docker).
 *
 * TODO: этот тест проверяет только наличие строки в HTML, но не проверяет
 *       HTTP-статус (200), Content-Type, отсутствие PHP-ошибок в выводе.
 *       Желательно добавить проверку кода ответа через get_headers().
 */
class CanaryTest extends TestCase
{
    /**
     * Проверяет базовую доступность веб-приложения.
     */
    public function testWebServerResponds(): void
    {
        // URL приложения: из переменной окружения или значение по умолчанию
        $url = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';

        // Настраиваем HTTP-контекст: таймаут 5с, следование редиректам
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
        ]);

        // Act: HTTP GET запрос (@ подавляет warning при недоступности)
        $response = @file_get_contents($url, false, $context);

        // Assert: сервер должен ответить
        $this->assertNotFalse($response, 'Web server must respond on ' . $url);
        // Assert: ответ должен содержать название приложения
        $this->assertStringContainsString('phpResticAdmin', $response ?? '', 'Response must contain "phpResticAdmin"');
    }
}
