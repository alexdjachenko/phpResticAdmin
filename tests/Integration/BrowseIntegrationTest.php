<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест просмотра содержимого снапшотов (restic ls).
 *
 * Цель: проверить, что restic ls корректно отображает содержимое
 *       корневого каталога и подкаталогов снапшота, и что NDJSON-вывод
 *       парсится правильно.
 *
 * Сценарий:
 *   1. Создаётся вложенная структура data/a/b/file.txt.
 *   2. Делается backup, получается snapshot ID.
 *   3. restic ls / — проверяется наличие директории a.
 *   4. restic ls data/a/b — проверяется наличие файла file.txt.
 *
 * Критерий успеха:
 *   - ls возвращает exitCode 0 для обоих путей.
 *   - В корне находится хотя бы одна директория.
 *   - В подкаталоге /a/b находится хотя бы один файл.
 *
 * Требует: restic в PATH.
 */
class BrowseIntegrationTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var CommandRunner */
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_browse_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->runner = new CommandRunner();

        // Инициализируем репозиторий
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        // Создаём вложенную структуру: data/a/b/file.txt
        $dataDir = $this->tmpDir . '/data';
        $subDir = $dataDir . '/a/b';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/file.txt', 'content');

        // Делаем backup
        $backupResult = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dataDir],
            ['RESTIC_PASSWORD' => '']
        );

        if ($backupResult['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to create backup: ' . $backupResult['stderr']);
        }

        // Получаем ID последнего снапшота
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password', '--last'],
            ['RESTIC_PASSWORD' => '']
        );
        $snapshots = json_decode($snapResult['stdout'], true) ?: [];
        $this->snapId = $snapshots[0]['id'] ?? '';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /** @var string ID снапшота для тестов browse */
    private string $snapId;

    /**
     * Проверяет просмотр корневого каталога снапшота.
     *
     * Ожидается: хотя бы одна директория (мы создали data/a/b/file.txt).
     */
    public function testBrowseRoot(): void
    {
        // Act: restic ls на корень снапшота
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $this->snapId, '/'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: команда успешна, парсим NDJSON
        $this->assertSame(0, $result['exitCode'], 'Browse should succeed: ' . $result['stderr']);
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries, 'Root should contain entries');

        // Assert: в корне есть хотя бы одна директория (data/)
        $dirs = array_filter($entries, function ($e): bool {
            return is_array($e) && ($e['type'] ?? '') === 'dir';
        });
        $this->assertNotEmpty($dirs, 'Should find at least one directory at root');
    }

    /**
     * Проверяет просмотр подкаталога снапшота.
     *
     * Путь data/a/b должен содержать file.txt.
     *
     * TODO: использование полного пути $dataDir . '/a/b' привязано к
     *       абсолютному пути бэкапа. Это корректно для restic (он хранит
     *       полные пути), но может быть неочевидно при чтении теста.
     */
    public function testBrowseSubdirectory(): void
    {
        $dataDir = $this->tmpDir . '/data';

        // Act: restic ls на подкаталог data/a/b
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $this->snapId, $dataDir . '/a/b'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: команда успешна
        $this->assertSame(0, $result['exitCode'], 'Browse subdir should succeed: ' . $result['stderr']);
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);

        // Assert: в подкаталоге есть хотя бы один файл
        $files = array_filter($entries, function ($e): bool {
            return is_array($e) && ($e['type'] ?? '') === 'file';
        });
        $this->assertNotEmpty($files, 'Should find at least one file in /a/b');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseNdjson(string $output): array
    {
        $entries = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'null') {
                continue;
            }
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
