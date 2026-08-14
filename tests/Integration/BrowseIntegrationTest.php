<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use App\Restic\SnapshotService;
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
 *   2. Делается backup (RepositoryService), получается snapshot ID (SnapshotService).
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
    /** @var string ID снапшота для тестов browse */
    private string $snapId;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_browse_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->runner = new CommandRunner();

        $repo = [
            'id' => 'browse-repo',
            'name' => 'Browse',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // Инициализируем репозиторий
        $repoService = new RepositoryService($this->runner);
        $result = $repoService->init($repo);
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }

        // Создаём вложенную структуру: data/a/b/file.txt
        $dataDir = $this->tmpDir . '/data';
        $subDir = $dataDir . '/a/b';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/file.txt', 'content');

        // Делаем backup
        $backupResult = $repoService->backupSync($repo, [$dataDir]);
        if (!$backupResult['ok']) {
            $this->markTestSkipped('Failed to create backup: ' . $backupResult['error']);
        }

        // Получаем ID последнего снапшота
        $snapService = new SnapshotService($this->runner);
        $snapshots = $snapService->listSnapshots($repo);
        $this->snapId = $snapshots[0]['id'] ?? '';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет просмотр корневого каталога снапшота.
     */
    public function testBrowseRoot(): void
    {
        $result = $this->runner->run(
            ['restic', '--insecure-no-password', '--repo', $this->repoDir, 'ls', '--json', $this->snapId, '/'],
            []
        );

        $this->assertSame(0, $result['exitCode'], 'Browse should succeed: ' . $result['stderr']);
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries, 'Root should contain entries');

        $dirs = array_filter($entries, function ($e): bool {
            return is_array($e) && ($e['type'] ?? '') === 'dir';
        });
        $this->assertNotEmpty($dirs, 'Should find at least one directory at root');
    }

    /**
     * Проверяет просмотр подкаталога снапшота.
     */
    public function testBrowseSubdirectory(): void
    {
        $dataDir = $this->tmpDir . '/data';

        $result = $this->runner->run(
            ['restic', '--insecure-no-password', '--repo', $this->repoDir, 'ls', '--json', $this->snapId, $dataDir . '/a/b'],
            []
        );

        $this->assertSame(0, $result['exitCode'], 'Browse subdir should succeed: ' . $result['stderr']);
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);

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
