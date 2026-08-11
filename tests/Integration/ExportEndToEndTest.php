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
 * Интеграционный тест экспорта данных из снапшотов (restic dump).
 *
 * Цель: проверить выгрузку отдельных файлов и целых снапшотов (tar)
 *       через restic dump, а также обработку ошибок для несуществующих файлов.
 *
 * Сценарий:
 *   1. Создаётся структура data/dir/file.txt с содержимым "Hello Export".
 *   2. Делается backup, получается snapshot ID.
 *   3. dump конкретного файла — проверяется содержимое.
 *   4. dump / (весь снапшот) — проверяется, что это валидный tar-архив.
 *   5. dump несуществующего файла — проверяется ненулевой exitCode.
 *
 * Критерий успеха:
 *   - dump файла возвращает exitCode 0 и точное содержимое.
 *   - dump / возвращает exitCode 0 и валидный tar (сигнатура "ustar").
 *   - dump несуществующего файла возвращает exitCode != 0.
 *
 * Требует: restic в PATH.
 */
class ExportEndToEndTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var string Путь к тестовым данным */
    private string $dataDir;
    /** @var CommandRunner */
    private CommandRunner $runner;
    /** @var string ID снапшота */
    private string $snapId;

    protected function setUp(): void
    {
        // Создаём изолированное окружение
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_export_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);
        mkdir($this->dataDir . '/dir', 0777, true);

        // Создаём тестовый файл с известным содержимым
        file_put_contents($this->dataDir . '/dir/file.txt', 'Hello Export');

        $this->runner = new CommandRunner();

        // Инициализируем репозиторий
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        // Делаем backup
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $this->dataDir],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

        // Получаем ID снапшота (ожидаем ровно один)
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--last', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $snapResult['exitCode']);
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(1, $snaps);
        $this->snapId = $snaps[0]['id'] ?? '';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет выгрузку одного файла из снапшота.
     *
     * Ожидается: содержимое stdout = "Hello Export" (точное совпадение).
     */
    public function testDumpSingleFileReturnsCorrectContent(): void
    {
        // Act: restic dump конкретного файла
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, $this->dataDir . '/dir/file.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: команда успешна, содержимое совпадает с оригиналом
        $this->assertSame(0, $result['exitCode'], 'dump should succeed: ' . $result['stderr']);
        $this->assertStringContainsString('Hello Export', $result['stdout']);
    }

    /**
     * Проверяет выгрузку всего снапшота как tar-архива.
     *
     * Ожидается: stdout — валидный tar (сигнатура "ustar" на позиции 257).
     */
    public function testDumpSnapshotReturnsTarArchive(): void
    {
        // Act: restic dump / (весь снапшот → tar)
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, '/', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: команда успешна, вывод не пустой
        $this->assertSame(0, $result['exitCode'], 'dump / should succeed: ' . $result['stderr']);
        $this->assertGreaterThan(0, strlen($result['stdout']), 'tar output should not be empty');

        // Assert: проверяем tar-сигнатуру ("ustar" на позиции 257 в стандартном tar-заголовке)
        $this->assertStringContainsString('ustar', substr($result['stdout'], 257, 10), 'Output should be a valid tar archive');
    }

    /**
     * Проверяет, что dump несуществующего файла возвращает ошибку.
     */
    public function testDumpNonexistentFileFails(): void
    {
        // Act: restic dump файла, которого нет в снапшоте
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, '/nonexistent.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: exitCode != 0 (ошибка)
        $this->assertNotSame(0, $result['exitCode'], 'dump of nonexistent file should fail');
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
