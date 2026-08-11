<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\SnapshotService;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест резервного копирования (restic backup).
 *
 * Цель: проверить, что операция backup создаёт снапшоты в restic-репозитории
 *       и что SnapshotService способен их обнаружить и прочитать.
 *
 * Сценарий:
 *   1. Инициализируется временный restic-репозиторий без пароля.
 *   2. Создаются тестовые файлы/директории.
 *   3. Выполняется restic backup через CommandRunner.
 *   4. Через SnapshotService::listSnapshots() проверяется наличие снапшота.
 *
 * Критерий успеха:
 *   - exitCode backup = 0 (команда завершилась без ошибок).
 *   - SnapshotService возвращает непустой массив снапшотов.
 *   - Каждый снапшот содержит ключевые поля: short_id, paths, summary.
 *
 * Требует: restic в PATH (тест запускается только в CI).
 */
class BackupServiceTest extends TestCase
{
    /** @var string Временная директория для всего теста */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию внутри tmpDir */
    private string $repoDir;
    /** @var CommandRunner Обёртка для вызова restic CLI */
    private CommandRunner $runner;

    protected function setUp(): void
    {
        // Создаём изолированную временную директорию
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_backup_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->runner = new CommandRunner();

        // Инициализируем restic-репозиторий без пароля
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Если restic недоступен (например, локально) — пропускаем тест
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет, что backup одного каталога создаёт снапшот в репозитории.
     *
     * Сценарий: создаём каталог с одним файлом, делаем backup,
     *            затем через SnapshotService проверяем наличие снапшота.
     */
    public function testBackupCreatesSnapshot(): void
    {
        // Arrange: создаём тестовый каталог с одним файлом
        $dataDir = $this->tmpDir . '/data';
        mkdir($dataDir, 0777, true);
        file_put_contents($dataDir . '/hello.txt', 'Hello World');

        // Act: выполняем backup через restic CLI
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dataDir],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: backup должен завершиться успешно
        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

        // Assert: SnapshotService должен увидеть созданный снапшот
        $snapService = new SnapshotService($this->runner);
        $snapshots = $snapService->listSnapshots([
            'path' => $this->repoDir,
            'password' => null,
        ]);

        $this->assertNotEmpty($snapshots, 'Should have at least one snapshot after backup');
        // Проверяем, что снапшот содержит минимально необходимые поля
        $this->assertArrayHasKey('short_id', $snapshots[0]);
    }

    /**
     * Проверяет backup нескольких независимых каталогов одной командой.
     *
     * Сценарий: создаём два каталога path1 и path2 с разными файлами,
     *            делаем backup обоих одной командой, проверяем что paths
     *            в снапшоте содержат оба пути.
     */
    public function testBackupMultiplePaths(): void
    {
        // Arrange: создаём два независимых каталога
        $dir1 = $this->tmpDir . '/path1';
        $dir2 = $this->tmpDir . '/path2';
        mkdir($dir1, 0777, true);
        mkdir($dir2, 0777, true);
        file_put_contents($dir1 . '/a.txt', 'A');
        file_put_contents($dir2 . '/b.txt', 'B');

        // Act: backup двух каталогов одной командой
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dir1, $dir2],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

        // Assert: снапшот должен содержать оба пути
        $snapService = new SnapshotService($this->runner);
        $snapshots = $snapService->listSnapshots([
            'path' => $this->repoDir,
            'password' => null,
        ]);

        $this->assertNotEmpty($snapshots);
        $paths = $snapshots[0]['paths'] ?? [];
        $this->assertCount(2, $paths, 'Should contain exactly 2 backup paths');
        $this->assertContains($dir1, $paths, 'First backup path should be present');
        $this->assertContains($dir2, $paths, 'Second backup path should be present');
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
