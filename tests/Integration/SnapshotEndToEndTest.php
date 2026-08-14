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
 * Комплексный end-to-end интеграционный тест снапшотов.
 *
 * Цель: проверить полный жизненный цикл снапшотов — от создания до удаления —
 *       с проверкой содержимого, размеров, изоляции данных между снапшотами,
 *       экспорта и обслуживания (forget + check).
 *
 * Сценарий (в порядке выполнения тестов):
 *   1. Создаются 3 снапшота с контролируемым содержимым:
 *      - backup-1: file_a.txt (100 байт), file_b.txt (200 байт)
 *      - backup-2: file_a.txt изменён (150 байт), file_b.txt без изменений
 *      - backup-3: добавлен file_c.txt (50 байт)
 *
 *   2. Статистика (stats) — через SnapshotService::getStats.
 *   3. Содержимое (ls) — через CommandRunner (нет отдельного сервиса).
 *   4. Экспорт (dump) — через CommandRunner.
 *   5. Обслуживание (forget + check) — через MaintenanceService.
 *
 * Критерий успеха:
 *   - Все операции возвращают ожидаемые ok/exitCode.
 *   - Размеры и содержимое файлов строго соответствуют ожидаемым.
 *   - Данные разных снапшотов изолированы.
 *   - forget корректно удаляет указанные снапшоты.
 *
 * Важно: тесты выполняются последовательно, деструктивные операции
 *        (forget) идут последними.
 *
 * Требует: restic в PATH.
 */
class SnapshotEndToEndTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var string Путь к тестовым данным */
    private string $dataDir;
    /** @var CommandRunner */
    private CommandRunner $runner;
    /** @var array<string, mixed> Конфигурация репозитория */
    private array $repo;

    /**
     * Ожидаемое содержимое каждого снапшота.
     * @var array<int, array{id: string, short_id: string, time: string, dir1Files: array<string, int>, dir2Files: array<string, int>}>
     */
    private array $snapshots = [];

    protected function setUp(): void
    {
        // Создаём изолированное окружение
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_e2e_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Две поддиректории для проверки multi-path backup
        mkdir($this->dataDir . '/dir1', 0777, true);
        mkdir($this->dataDir . '/dir2', 0777, true);

        $this->runner = new CommandRunner();

        $this->repo = [
            'id' => 'e2e-repo',
            'name' => 'E2E Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // Инициализируем репозиторий
        $repoService = new RepositoryService($this->runner);
        $result = $repoService->init($this->repo);
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }

        // === Backup 1: file_a.txt = 100 байт 'A', file_b.txt = 200 байт 'B' ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('A', 100));
        file_put_contents($this->dataDir . '/dir2/file_b.txt', str_repeat('B', 200));

        $this->backup();
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 100],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 2: file_a.txt изменён (150 байт 'X'), file_b.txt без изменений ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('X', 150));

        $this->backup();
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 3: добавлен file_c.txt (50 байт 'C') ===
        file_put_contents($this->dataDir . '/dir1/file_c.txt', str_repeat('C', 50));

        $this->backup();
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150, 'file_c.txt' => 50],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // Получаем реальные ID снапшотов из restic и заполняем ожидаемые данные
        $snapService = new SnapshotService($this->runner);
        $snaps = $snapService->listSnapshots($this->repo);
        $this->assertIsArray($snaps);
        $this->assertCount(3, $snaps, 'Should have exactly 3 snapshots');

        // Сортируем по времени (от старого к новому)
        usort($snaps, function (array $a, array $b): int {
            return ($a['time'] ?? '') <=> ($b['time'] ?? '');
        });

        foreach ($snaps as $i => $snap) {
            $this->snapshots[$i]['id'] = $snap['id'] ?? '';
            $this->snapshots[$i]['short_id'] = $snap['short_id'] ?? '';
            $this->snapshots[$i]['time'] = $snap['time'] ?? '';
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ========================
    // Stats tests
    // ========================

    /**
     * Проверяет, что все три снапшота имеют ненулевой размер (total_size).
     */
    public function testAllSnapshotsHaveNonZeroSize(): void
    {
        $snapService = new SnapshotService($this->runner);
        $seenIds = [];
        foreach ($this->snapshots as $snap) {
            $entry = $snapService->getStats($this->repo, $snap['id']);
            $this->assertIsArray($entry, "Stats for snapshot " . ($snap['short_id'] ?? '?') . " should be an array");

            $seenIds[] = $snap['short_id'];

            // Assert: total_size присутствует и больше нуля
            $this->assertArrayHasKey('total_size', $entry, "Stats entry should have total_size");
            $this->assertGreaterThan(0, $entry['total_size'], "Snapshot should have non-zero size");
        }

        $this->assertCount(3, $seenIds, 'Should have stats for all 3 snapshots');
    }

    /**
     * Проверяет, что размеры снапшотов кумулятивно растут.
     */
    public function testSnapshotSizesIncreaseWithNewData(): void
    {
        $snapService = new SnapshotService($this->runner);
        $sizes = [];

        foreach ($this->snapshots as $snap) {
            $entry = $snapService->getStats($this->repo, $snap['id']);
            $this->assertIsArray($entry);
            $sizes[$snap['id']] = $entry['total_size'] ?? 0;
        }

        $size1 = $sizes[$this->snapshots[0]['id']];
        $size2 = $sizes[$this->snapshots[1]['id']];
        $size3 = $sizes[$this->snapshots[2]['id']];

        $this->assertGreaterThan(0, $size1, 'Backup 1 should have non-zero size');
        $this->assertGreaterThan(0, $size2, 'Backup 2 should have non-zero size');
        $this->assertGreaterThan(0, $size3, 'Backup 3 should have non-zero size');

        $this->assertGreaterThan($size1, $size3,
            'Backup 3 must be larger than backup 1 (added file_c.txt with new data)'
        );
    }

    // ========================
    // File content tests
    // ========================

    /**
     * Проверяет, что каждый снапшот содержит ожидаемые файлы точного размера.
     */
    public function testEachSnapshotContainsExpectedFiles(): void
    {
        foreach ($this->snapshots as $snapIndex => $snap) {
            $sid = $snap['short_id'];

            $lsResult = $this->lsPath($snap['id'], $this->dataDir . '/dir1');
            foreach ($snap['dir1Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) dir1 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in dir1 should have size $expectedSize"
                );
            }

            $lsResult = $this->lsPath($snap['id'], $this->dataDir . '/dir2');
            foreach ($snap['dir2Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) dir2 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in dir2 should have size $expectedSize"
                );
            }
        }
    }

    /**
     * Проверяет изоляцию данных между снапшотами.
     */
    public function testFilesAreNotMixedBetweenSnapshots(): void
    {
        $d1 = $this->dataDir . '/dir1';
        $d2 = $this->dataDir . '/dir2';

        $files1 = $this->lsPath($this->snapshots[0]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files1);
        $this->assertSame(100, $files1['file_a.txt'], 'backup-1 file_a should be 100 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files1, 'backup-1 should NOT contain file_c.txt');

        $files2 = $this->lsPath($this->snapshots[1]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files2);
        $this->assertSame(150, $files2['file_a.txt'], 'backup-2 file_a should be 150 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files2, 'backup-2 should NOT contain file_c.txt');

        $files3 = $this->lsPath($this->snapshots[2]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files3);
        $this->assertSame(150, $files3['file_a.txt'], 'backup-3 file_a should be 150 bytes');
        $this->assertArrayHasKey('file_c.txt', $files3);
        $this->assertSame(50, $files3['file_c.txt'], 'backup-3 file_c should be 50 bytes');

        foreach ([0, 1, 2] as $i) {
            $files = $this->lsPath($this->snapshots[$i]['id'], $d2);
            $this->assertArrayHasKey('file_b.txt', $files);
            $this->assertSame(200, $files['file_b.txt'], "backup-" . ($i + 1) . " file_b should be 200 bytes");
        }
    }

    // ========================
    // Export tests
    // ========================

    /**
     * Проверяет экспорт одного файла: сравнение содержимого с ожидаемым.
     */
    public function testExportSingleFile(): void
    {
        $snapId = $this->snapshots[2]['id'];
        $result = $this->runner->run(
            ['restic', '--insecure-no-password', '--repo', $this->repoDir, 'dump', $snapId, $this->dataDir . '/dir1/file_a.txt'],
            []
        );

        $this->assertSame(0, $result['exitCode'], 'dump file_a.txt should succeed: ' . $result['stderr']);
        $this->assertEquals(str_repeat('X', 150), $result['stdout'], 'file_a.txt should contain exactly 150 X characters');
    }

    /**
     * Проверяет экспорт всего снапшота как tar-архива.
     */
    public function testExportEntireSnapshot(): void
    {
        $snapId = $this->snapshots[1]['id'];
        $result = $this->runner->run(
            ['restic', '--insecure-no-password', '--repo', $this->repoDir, 'dump', $snapId, '/'],
            []
        );

        $this->assertSame(0, $result['exitCode'], 'dump / should succeed: ' . $result['stderr']);
        $this->assertGreaterThan(0, strlen($result['stdout']), 'tar output should not be empty');
        $this->assertStringContainsString('ustar', substr($result['stdout'], 257, 10), 'Output should be a valid tar archive');
    }

    // ========================
    // Maintenance tests
    // ========================

    /**
     * Проверяет, что forget --keep-last 2 сохраняет 2 последних снапшота.
     *
     * Важно: тест НЕОБРАТИМО удаляет backup-1.
     */
    public function testForgetKeepLastKeepsCorrectSnapshots(): void
    {
        $maintService = new MaintenanceService($this->runner);
        $result = $maintService->forget($this->repo, ['keep_last' => 2]);
        $this->assertTrue($result['ok'], 'forget should succeed: ' . $result['error']);

        $snapService = new SnapshotService($this->runner);
        $snaps = $snapService->listSnapshots($this->repo);
        $this->assertCount(2, $snaps, 'should keep exactly 2 snapshots');

        $remainingIds = array_map(function (array $s): string {
            return $s['id'] ?? '';
        }, $snaps);
        $this->assertContains($this->snapshots[1]['id'], $remainingIds, 'backup-2 should be kept');
        $this->assertContains($this->snapshots[2]['id'], $remainingIds, 'backup-3 should be kept');
        $this->assertNotContains($this->snapshots[0]['id'], $remainingIds, 'backup-1 should be removed');
    }

    /**
     * Проверяет, что после forget --keep-last 1 + check репозиторий остаётся целостным.
     */
    public function testForgetThenCheck(): void
    {
        $maintService = new MaintenanceService($this->runner);

        $result = $maintService->forget($this->repo, ['keep_last' => 1]);
        $this->assertTrue($result['ok'], 'forget should succeed: ' . $result['error']);

        $check = $maintService->check($this->repo);
        $this->assertTrue($check['ok'], 'check after forget should succeed: ' . $check['error']);
    }

    /**
     * Проверяет, что unlock на чистом репозитории завершается успешно.
     */
    public function testUnlockSucceeds(): void
    {
        $maintService = new MaintenanceService($this->runner);
        $result = $maintService->unlock($this->repo);
        $this->assertTrue($result['ok'], 'unlock on clean repo should succeed');
    }

    // ========================
    // Helpers
    // ========================

    private function backup(): void
    {
        $repoService = new RepositoryService($this->runner);
        $result = $repoService->backupSync($this->repo, [$this->dataDir]);
        $this->assertTrue($result['ok'], 'Backup should succeed: ' . $result['error']);
    }

    /**
     * @return array<string, int>
     */
    private function lsPath(string $snapId, string $path): array
    {
        $result = $this->runner->run(
            ['restic', '--insecure-no-password', '--repo', $this->repoDir, 'ls', '--json', $snapId, $path],
            []
        );

        $this->assertSame(0, $result['exitCode'], "ls $path should succeed: " . ($result['stderr'] ?? ''));

        $files = [];
        $lines = explode("\n", trim($result['stdout']));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'null') {
                continue;
            }
            $entry = json_decode($line, true);
            if (is_array($entry) && !empty($entry['name']) && ($entry['type'] ?? '') === 'file') {
                $files[$entry['name']] = $entry['size'] ?? 0;
            }
        }
        return $files;
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
