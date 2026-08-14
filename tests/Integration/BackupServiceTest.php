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
 * Интеграционный тест резервного копирования (restic backup).
 *
 * Цель: проверить, что операция backup создаёт снапшоты в restic-репозитории
 *       и что SnapshotService способен их обнаружить и прочитать.
 *
 * Сценарий:
 *   1. Инициализируется временный restic-репозиторий без пароля (RepositoryService::init).
 *   2. Создаются тестовые файлы/директории.
 *   3. Выполняется restic backup через RepositoryService::backupSync.
 *   4. Через SnapshotService::listSnapshots() проверяется наличие снапшота.
 *
 * Критерий успеха:
 *   - backup завершается успешно.
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
    /** @var array<string, mixed> Конфигурация тестового репозитория */
    private array $repo;

    protected function setUp(): void
    {
        // Создаём изолированную временную директорию
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_backup_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test Repo',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // Инициализируем restic-репозиторий без пароля через сервис
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->init($this->repo);

        // Если restic недоступен (например, локально) — пропускаем тест
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет, что backup одного каталога создаёт снапшот в репозитории.
     */
    public function testBackupCreatesSnapshot(): void
    {
        // Arrange: создаём тестовый каталог с одним файлом
        $dataDir = $this->tmpDir . '/data';
        mkdir($dataDir, 0777, true);
        file_put_contents($dataDir . '/hello.txt', 'Hello World');

        // Act: выполняем backup через RepositoryService
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->backupSync($this->repo, [$dataDir]);

        // Assert: backup должен завершиться успешно
        $this->assertTrue($result['ok'], 'Backup should succeed: ' . $result['error']);

        // Assert: SnapshotService должен увидеть созданный снапшот
        $snapService = new SnapshotService(new CommandRunner());
        $snapshots = $snapService->listSnapshots($this->repo);

        $this->assertNotEmpty($snapshots, 'Should have at least one snapshot after backup');
        $this->assertArrayHasKey('short_id', $snapshots[0]);
    }

    /**
     * Проверяет backup нескольких независимых каталогов одной командой.
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
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->backupSync($this->repo, [$dir1, $dir2]);

        $this->assertTrue($result['ok'], 'Backup should succeed: ' . $result['error']);

        // Assert: снапшот должен содержать оба пути
        $snapService = new SnapshotService(new CommandRunner());
        $snapshots = $snapService->listSnapshots($this->repo);

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
