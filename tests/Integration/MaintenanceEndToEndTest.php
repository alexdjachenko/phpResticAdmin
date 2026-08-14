<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\MaintenanceService;
use App\Restic\RepositoryService;
use App\Restic\SnapshotService;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест обслуживания репозитория (check, forget, unlock, repair index).
 *
 * Цель: проверить основные maintenance-операции restic при реальном
 *       взаимодействии с CLI: проверка целостности, удаление старых
 *       снапшотов, снятие блокировки, перестроение индекса.
 *
 * Сценарий:
 *   1. Инициализируется репозиторий (RepositoryService::init), создаются 3 снапшота (RepositoryService::backupSync).
 *   2. check — проверка целостности (должна пройти успешно).
 *   3. forget --dry-run — проверка, что снапшоты НЕ удаляются.
 *   4. forget --keep-last 1 — реальное удаление, остаётся 1 снапшот.
 *   5. unlock — снятие блокировки на чистом репо.
 *   6. repair index — перестроение/восстановление индекса.
 *
 * Критерий успеха:
 *   - check возвращает ok=true.
 *   - forget --dry-run не меняет количество снапшотов.
 *   - forget --keep-last 1 оставляет ровно 1 снапшот.
 *   - unlock и repair index возвращают ok=true.
 *
 * Важно: тесты выполняются последовательно и модифицируют состояние
 *        репозитория (forget реально удаляет снапшоты). Порядок тестов
 *        важен: check и dry-run должны идти до деструктивного forget.
 *
 * Требует: restic в PATH.
 */
class MaintenanceEndToEndTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var string Путь к тестовым данным */
    private string $dataDir;
    /** @var array<string, mixed> Конфигурация тестового репозитория */
    private array $repo;

    protected function setUp(): void
    {
        // Создаём изолированное окружение
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_maint_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Создаём три подкаталога с разными файлами для трёх снапшотов
        mkdir($this->dataDir . '/a', 0777, true);
        mkdir($this->dataDir . '/b', 0777, true);
        mkdir($this->dataDir . '/c', 0777, true);

        $this->repo = [
            'id' => 'maint-repo',
            'name' => 'Maintenance',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // Инициализируем репозиторий
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->init($this->repo);
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }

        // Создаём 3 снапшота
        file_put_contents($this->dataDir . '/a/f1.txt', 'data1');
        $this->backup();

        file_put_contents($this->dataDir . '/b/f2.txt', 'data2');
        $this->backup();

        file_put_contents($this->dataDir . '/c/f3.txt', 'data3');
        $this->backup();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет, что restic check проходит успешно на целом репозитории.
     */
    public function testCheckRunsSuccessfully(): void
    {
        $service = new MaintenanceService(new CommandRunner());
        $result = $service->check($this->repo);

        $this->assertTrue($result['ok'], 'check should succeed: ' . $result['error']);
    }

    /**
     * Проверяет, что forget --dry-run НЕ удаляет снапшоты.
     */
    public function testForgetDryRunDoesNotDelete(): void
    {
        $snapService = new SnapshotService(new CommandRunner());
        $count = count($snapService->listSnapshots($this->repo));

        $service = new MaintenanceService(new CommandRunner());
        $result = $service->forget($this->repo, ['keep_last' => 1, 'dry_run' => true]);
        $this->assertTrue($result['ok'], 'forget dry-run should succeed: ' . $result['error']);

        $this->assertSame($count, count($snapService->listSnapshots($this->repo)), 'dry-run should not delete snapshots');
    }

    /**
     * Проверяет реальное удаление снапшотов: forget --keep-last 1.
     *
     * Важно: этот тест НЕОБРАТИМО изменяет состояние репозитория.
     */
    public function testForgetWithKeepLast(): void
    {
        $service = new MaintenanceService(new CommandRunner());
        $result = $service->forget($this->repo, ['keep_last' => 1]);
        $this->assertTrue($result['ok'], 'forget should succeed: ' . $result['error']);

        $snapService = new SnapshotService(new CommandRunner());
        $this->assertCount(1, $snapService->listSnapshots($this->repo), 'forget --keep-last 1 should leave 1 snapshot');
    }

    /**
     * Проверяет, что unlock на чистом (не заблокированном) репозитории завершается успешно.
     */
    public function testUnlockOnCleanRepoSucceeds(): void
    {
        $service = new MaintenanceService(new CommandRunner());
        $result = $service->unlock($this->repo);

        $this->assertTrue($result['ok'], 'unlock on clean repo should succeed');
    }

    /**
     * Проверяет перестроение индекса (restic repair index).
     */
    public function testRebuildIndexSucceeds(): void
    {
        $service = new MaintenanceService(new CommandRunner());
        $result = $service->rebuildIndex($this->repo);

        $this->assertTrue($result['ok'], 'repair index should succeed: ' . $result['error']);
    }

    private function backup(): void
    {
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->backupSync($this->repo, [$this->dataDir]);
        $this->assertTrue($result['ok'], 'Backup should succeed: ' . $result['error']);
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
