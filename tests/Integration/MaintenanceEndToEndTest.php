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
 * Интеграционный тест обслуживания репозитория (check, forget, unlock, rebuild-index).
 *
 * Цель: проверить основные maintenance-операции restic при реальном
 *       взаимодействии с CLI: проверка целостности, удаление старых
 *       снапшотов, снятие блокировки, перестроение индекса.
 *
 * Сценарий:
 *   1. Инициализируется репозиторий, создаются 3 снапшота с тегами.
 *   2. check — проверка целостности (должна пройти успешно).
 *   3. forget --dry-run — проверка, что снапшоты НЕ удаляются.
 *   4. forget --keep-last 1 — реальное удаление, остаётся 1 снапшот.
 *   5. unlock — снятие блокировки на чистом репо.
 *   6. rebuild-index — перестроение индекса.
 *
 * Критерий успеха:
 *   - check возвращает exitCode 0.
 *   - forget --dry-run не меняет количество снапшотов.
 *   - forget --keep-last 1 оставляет ровно 1 снапшот.
 *   - unlock и rebuild-index возвращают exitCode 0.
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
    /** @var CommandRunner */
    private CommandRunner $runner;

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

        $this->runner = new CommandRunner();

        // Инициализируем репозиторий
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        // Создаём 3 снапшота с разными тегами для тестов forget
        file_put_contents($this->dataDir . '/a/f1.txt', 'data1');
        $this->backup('tag1');

        file_put_contents($this->dataDir . '/b/f2.txt', 'data2');
        $this->backup('tag2');

        file_put_contents($this->dataDir . '/c/f3.txt', 'data3');
        $this->backup('tag3');
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
        // Act: запускаем проверку целостности
        $result = $this->runner->run(
            ['restic', 'check', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: проверка прошла без ошибок
        $this->assertSame(0, $result['exitCode'], 'check should succeed: ' . $result['stderr']);
    }

    /**
     * Проверяет, что forget --dry-run НЕ удаляет снапшоты.
     */
    public function testForgetDryRunDoesNotDelete(): void
    {
        // Запоминаем исходное количество снапшотов
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $count = count($snaps);

        // Act: forget --dry-run --keep-last 1
        $result = $this->runner->run(
            ['restic', 'forget', '--dry-run', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget dry-run should succeed: ' . $result['stderr']);

        // Assert: количество снапшотов не изменилось
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snapsAfter = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snapsAfter);
        $this->assertSame($count, count($snapsAfter), 'dry-run should not delete snapshots');
    }

    /**
     * Проверяет реальное удаление снапшотов: forget --keep-last 1.
     *
     * Важно: этот тест НЕОБРАТИМО изменяет состояние репозитория.
     *        Последующие тесты в этом классе не должны рассчитывать
     *        на исходные 3 снапшота.
     */
    public function testForgetWithKeepLast(): void
    {
        // Act: реальное удаление — оставляем только последний снапшот
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        // Assert: остался ровно 1 снапшот
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(1, $snaps, 'forget --keep-last 1 should leave 1 snapshot');
    }

    /**
     * Проверяет, что unlock на чистом (не заблокированном) репозитории
     * завершается успешно (exitCode 0).
     */
    public function testUnlockOnCleanRepoSucceeds(): void
    {
        // Act: unlock на чистом репо
        $result = $this->runner->run(
            ['restic', 'unlock', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: операция успешна
        $this->assertSame(0, $result['exitCode'], 'unlock on clean repo should succeed');
    }

    /**
     * Проверяет перестроение индекса (rebuild-index).
     */
    public function testRebuildIndexSucceeds(): void
    {
        // Act: перестраиваем индекс
        $result = $this->runner->run(
            ['restic', 'rebuild-index', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: операция успешна
        $this->assertSame(0, $result['exitCode'], 'rebuild-index should succeed: ' . $result['stderr']);
    }

    private function backup(string $tag): void
    {
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', '--tag', $tag, $this->dataDir],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], "Backup '$tag' should succeed: " . $result['stderr']);
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
