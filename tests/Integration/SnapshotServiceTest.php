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
 * Интеграционный тест SnapshotService (работа со снапшотами restic).
 *
 * Цель: проверить операции listSnapshots, getSnapshot, addTag, removeTag,
 *       copy (копирование снапшота между репозиториями) при реальном
 *       взаимодействии с restic CLI.
 *
 * Сценарий:
 *   1. Инициализируется репозиторий, создаётся тестовый файл и backup.
 *   2. Тестируется listSnapshots (структура ответа, обязательные поля).
 *   3. Тестируется getSnapshot по short_id.
 *   4. Тестируется добавление и удаление тегов (addTag/removeTag).
 *   5. Тестируется копирование снапшота во второй репозиторий.
 *   6. Тестируется копирование с пустым ID (должно вернуть ошибку).
 *
 * Критерий успеха:
 *   - listSnapshots возвращает массив с ключами id, short_id, time, paths, summary.
 *   - getSnapshot находит снапшот по short_id.
 *   - Тег добавляется и удаляется (проверяется через перечитывание списка).
 *   - Копирование создаёт снапшот в целевом репозитории.
 *   - Копирование с пустым ID возвращает ok=false.
 *
 * Требует: restic в PATH.
 */
class SnapshotServiceTest extends TestCase
{
    /** @var string Временная директория для всего теста */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var array<string, mixed> Конфигурация тестового репозитория */
    private array $repo;

    protected function setUp(): void
    {
        // Создаём изолированную временную директорию
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_snap_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Инициализируем restic-репозиторий без пароля
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->init([
            'id' => 'test-repo',
            'name' => 'Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ]);

        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }

        // Создаём тестовый файл и делаем backup — чтобы в репозитории был хотя бы один снапшот
        $testDir = $this->tmpDir . '/data';
        mkdir($testDir, 0777, true);
        file_put_contents($testDir . '/test.txt', 'Hello World');

        $backupResult = $repoService->backupSync([
            'id' => 'test-repo',
            'name' => 'Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ], [$testDir]);

        if (!$backupResult['ok']) {
            $this->markTestSkipped('Failed to create backup: ' . $backupResult['error']);
        }

        // Конфигурация репозитория для SnapshotService
        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет listSnapshots: структуру ответа и наличие обязательных полей.
     *
     * Ожидается: массив с хотя бы одним снапшотом, содержащим поля
     * id, short_id, time, paths, summary (с total_bytes_processed).
     */
    public function testListSnapshots(): void
    {
        $service = new SnapshotService(new CommandRunner());
        $snapshots = $service->listSnapshots($this->repo);

        // Assert: результат — непустой массив
        $this->assertIsArray($snapshots);
        $this->assertNotEmpty($snapshots, 'Should have at least one snapshot');

        // Assert: первый снапшот содержит все обязательные поля
        $snap = $snapshots[0];
        $this->assertArrayHasKey('id', $snap);
        $this->assertArrayHasKey('short_id', $snap);
        $this->assertArrayHasKey('time', $snap);
        $this->assertArrayHasKey('paths', $snap);
        $this->assertArrayHasKey('summary', $snap);
        $this->assertArrayHasKey('total_bytes_processed', $snap['summary']);
    }

    /**
     * Проверяет получение одного снапшота по short_id.
     */
    public function testGetSnapshot(): void
    {
        $service = new SnapshotService(new CommandRunner());

        // Получаем список, берём short_id первого снапшота
        $snapshots = $service->listSnapshots($this->repo);
        $this->assertNotEmpty($snapshots);
        $shortId = $snapshots[0]['short_id'];

        // Act: получаем снапшот по short_id
        $snap = $service->getSnapshot($this->repo, $shortId);

        // Assert: снапшот найден, short_id совпадает
        $this->assertNotNull($snap);
        $this->assertSame($shortId, $snap['short_id']);
    }

    /**
     * Проверяет полный цикл addTag → removeTag.
     *
     * Важно: restic меняет ID снапшота при изменении тегов, поэтому
     * после каждой операции нужно перечитывать список снапшотов.
     */
    public function testAddAndRemoveTag(): void
    {
        $service = new SnapshotService(new CommandRunner());

        // Исходное состояние: получаем ID единственного снапшота
        $snapshots = $service->listSnapshots($this->repo);
        $this->assertNotEmpty($snapshots);
        $snapId = $snapshots[0]['id'];

        // Шаг 1: добавляем тег
        $result = $service->addTag($this->repo, $snapId, 'test-tag-xyz');
        $this->assertTrue($result['ok'], 'Add tag should succeed: ' . ($result['error'] ?? ''));

        // Шаг 2: перечитываем (ID мог измениться) и проверяем наличие тега
        $snapshots = $service->listSnapshots($this->repo);
        $snapId = $snapshots[0]['id'];
        $tags = $snapshots[0]['tags'] ?? [];
        $this->assertContains('test-tag-xyz', $tags, 'Tag should be present after add');

        // Шаг 3: удаляем тег (используем свежий ID)
        $result = $service->removeTag($this->repo, $snapId, 'test-tag-xyz');
        $this->assertTrue($result['ok'], 'Remove tag should succeed: ' . ($result['error'] ?? ''));

        // Шаг 4: перечитываем и проверяем, что тега больше нет
        $snapshots = $service->listSnapshots($this->repo);
        $tags = $snapshots[0]['tags'] ?? [];
        $this->assertNotContains('test-tag-xyz', $tags, 'Tag should be removed');
    }

    /**
     * Проверяет копирование снапшота из одного репозитория в другой.
     *
     * Сценарий: создаётся второй (пустой) репозиторий, в него копируется
     *            снапшот из основного. После копирования целевой репозиторий
     *            должен содержать хотя бы один снапшот.
     */
    public function testCopySnapshot(): void
    {
        $runner = new CommandRunner();

        // Arrange: создаём и инициализируем целевой репозиторий
        $destDir = $this->tmpDir . '/dest-repo';
        mkdir($destDir, 0777, true);

        $repoService = new RepositoryService($runner);
        $initResult = $repoService->init([
            'id' => 'test-dest',
            'name' => 'Dest',
            'type' => 'local',
            'path' => $destDir,
            'password' => null,
        ]);

        if (!$initResult['ok']) {
            $this->markTestSkipped('Failed to init dest restic repo: ' . $initResult['error']);
        }

        $service = new SnapshotService($runner);

        // Получаем ID снапшота из исходного репозитория
        $snapshots = $service->listSnapshots($this->repo);
        $this->assertNotEmpty($snapshots, 'Source repo should have at least one snapshot');
        $snapId = $snapshots[0]['id'];

        $destRepo = [
            'id' => 'test-dest',
            'name' => 'Dest',
            'type' => 'local',
            'path' => $destDir,
            'password' => null,
        ];

        // Act: копируем снапшот
        $result = $service->copy($this->repo, $destRepo, $snapId);

        // Assert: копирование успешно, в целевом репо есть снапшот
        $this->assertTrue($result['ok'], 'Copy should succeed: ' . ($result['error'] ?? ''));

        $destSnapshots = $service->listSnapshots($destRepo);
        $this->assertNotEmpty($destSnapshots, 'Dest repo should have at least one snapshot after copy');
    }

    /**
     * Проверяет, что copy с пустым snapshot ID возвращает ошибку.
     */
    public function testCopySnapshotWithEmptyId(): void
    {
        $service = new SnapshotService(new CommandRunner());
        $destRepo = [
            'id' => 'test-dest',
            'name' => 'Dest',
            'type' => 'local',
            'path' => '/nonexistent',
            'password' => null,
        ];

        // Act: копирование с пустым ID
        $result = $service->copy($this->repo, $destRepo, '');

        // Assert: должно вернуть ошибку с непустым сообщением
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error'], 'Error message should not be empty for empty snapshot ID');
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
