<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест контракта запуска фоновых задач через HTTP.
 *
 * Цель: тяжёлые операции стартуют через fetch (AJAX) и ОБЯЗАНЫ получать JSON
 *       даже при отказе (истёк CSRF, нет прав). Если на AJAX-запрос приходит
 *       редирект/HTML, фронтенд не может разобрать ответ и показывает общую
 *       ошибку «не удалось загрузить вывод задачи» вместо настоящей причины —
 *       именно этот регресс ловится тестом.
 *
 * Сценарий:
 *   1. POST /maintenance/check с заголовками AJAX и без CSRF-токена
 *      → 403 и JSON-тело {ok:false}.
 *   2. POST /repositories/backup с заголовками AJAX → 403 и JSON {ok:false}.
 *   3. GET /tasks/active с Accept: application/json → JSON {ok:...}.
 *
 * Критерий успеха:
 *   - ответы содержат Content-Type: application/json;
 *   - тело успешно парсится и содержит ключ ok.
 *
 * Требует: запущенный веб-сервер приложения (в CI — через Docker).
 * Тест НЕ требует входа: контракт «AJAX→JSON при отказе» проверяется и для гостя.
 */
class TaskEndpointContractTest extends TestCase
{
    private function baseUrl(): string
    {
        return rtrim(getenv('TEST_BASE_URL') ?: 'http://localhost:8080', '/');
    }

    /**
     * Выполняет HTTP-запрос и возвращает статус, Content-Type и тело.
     *
     * @param array<string, string> $headers
     * @return array{status: int, contentType: string, body: string}
     */
    private function request(string $method, string $path, array $headers = [], string $body = ''): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => false,
            ],
        ]);

        $response = @file_get_contents($this->baseUrl() . $path, false, $context);

        $status = 0;
        $contentType = '';
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $contentType = trim(substr($line, strlen('Content-Type:')));
            }
        }

        return ['status' => $status, 'contentType' => $contentType, 'body' => (string) $response];
    }

    /** AJAX-отказ на /maintenance/check приходит как JSON, не как редирект. */
    public function testMaintenanceCheckAjaxFailureIsJson(): void
    {
        $response = $this->request(
            'POST',
            '/maintenance/check',
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            'repo_id=whatever'
        );

        $this->assertStringContainsString('application/json', $response['contentType'], 'AJAX failure must be JSON, not a redirect/HTML');
        $decoded = json_decode($response['body'], true);
        $this->assertIsArray($decoded, 'body must be valid JSON: ' . $response['body']);
        $this->assertFalse($decoded['ok'], 'ok must be false on failure');
        $this->assertNotEmpty($decoded['error'], 'error message must be present');
    }

    /** AJAX-отказ на /repositories/backup также приходит как JSON. */
    public function testRepositoryBackupAjaxFailureIsJson(): void
    {
        $response = $this->request(
            'POST',
            '/repositories/backup?repo=whatever',
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            ''
        );

        $this->assertStringContainsString('application/json', $response['contentType'], 'AJAX failure must be JSON, not a redirect/HTML');
        $decoded = json_decode($response['body'], true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['ok']);
    }

    /** Индикатор активных задач отдаёт JSON. */
    public function testTasksActiveIsJson(): void
    {
        $response = $this->request('GET', '/tasks/active', ['Accept' => 'application/json']);

        $this->assertStringContainsString('application/json', $response['contentType']);
        $decoded = json_decode($response['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ok', $decoded);
        // С настроенным guest_user задача видна; без него — отказ, но всё равно JSON.
        if ($decoded['ok']) {
            $this->assertArrayHasKey('tasks', $decoded);
        }
    }
}
