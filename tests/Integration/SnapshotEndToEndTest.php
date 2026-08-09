<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class SnapshotEndToEndTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private string $dataDir;
    private CommandRunner $runner;
    /** @var array<string, mixed> */
    private array $repo;

    /** @var array<int, array{id: string, short_id: string, time: string, dir1Files: array<string, int>, dir2Files: array<string, int>}> */
    private array $snapshots = [];

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_e2e_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Плоская структура для простоты проверки
        mkdir($this->dataDir . '/dir1', 0777, true);
        mkdir($this->dataDir . '/dir2', 0777, true);

        $this->runner = new CommandRunner();

        // Init repo
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        $this->repo = [
            'id' => 'e2e-repo',
            'name' => 'E2E Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // === Backup 1: два файла в разных поддиректориях ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('A', 100));
        file_put_contents($this->dataDir . '/dir2/file_b.txt', str_repeat('B', 200));

        $this->backup('backup-1');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 100],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 2: file_a изменён, file_b без изменений ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('X', 150));

        $this->backup('backup-2');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 3: добавлен новый файл ===
        file_put_contents($this->dataDir . '/dir1/file_c.txt', str_repeat('C', 50));

        $this->backup('backup-3');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150, 'file_c.txt' => 50],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // Заполняем id из реального списка
        $snapList = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $snapList['exitCode'], 'Snapshot list should succeed');

        $snaps = json_decode($snapList['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(3, $snaps, 'Should have exactly 3 snapshots');

        // Сортируем по времени (старые first)
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
    // Тесты
    // ========================

    public function testAllSnapshotsHaveNonZeroSize(): void
    {
        $ids = array_map(fn($s) => $s['id'], $this->snapshots);
        $command = array_merge(
            ['restic', 'stats', '--json', '--mode', 'raw-data', '--repo', $this->repoDir, '--insecure-no-password'],
            $ids
        );
        $result = $this->runner->run($command, ['RESTIC_PASSWORD' => '']);

        $this->assertSame(0, $result['exitCode'], 'Stats should succeed: ' . $result['stderr']);
        $stats = json_decode($result['stdout'], true);
        $this->assertIsArray($stats);
        $this->assertCount(3, $stats, 'Stats should return 3 entries');

        $seenIds = [];
        foreach ($stats as $entry) {
            $sid = $entry['snapshot_id'] ?? $entry['id'] ?? '';
            $seenIds[] = $sid;
            $this->assertArrayHasKey('total_size', $entry, "Stats entry should have total_size");
            $this->assertGreaterThan(0, $entry['total_size'], "Snapshot should have non-zero size");
        }

        foreach ($this->snapshots as $snap) {
            $this->assertContains($snap['id'], $seenIds, 'Stats should include snapshot ' . ($snap['short_id'] ?? '?'));
        }
    }

    public function testSnapshotSizesIncreaseWithNewData(): void
    {
        $ids = array_map(fn($s) => $s['id'], $this->snapshots);
        $command = array_merge(
            ['restic', 'stats', '--json', '--mode', 'raw-data', '--repo', $this->repoDir, '--insecure-no-password'],
            $ids
        );
        $result = $this->runner->run($command, ['RESTIC_PASSWORD' => '']);

        $this->assertSame(0, $result['exitCode'], 'Stats should succeed');
        $stats = json_decode($result['stdout'], true);
        $this->assertIsArray($stats);

        $sizes = [];
        foreach ($stats as $entry) {
            $sid = $entry['snapshot_id'] ?? '';
            $sizes[$sid] = $entry['total_size'];
        }

        $size1 = $sizes[$this->snapshots[0]['id']];
        $size2 = $sizes[$this->snapshots[1]['id']];
        $size3 = $sizes[$this->snapshots[2]['id']];

        $this->assertGreaterThan(0, $size1, 'Backup 1 should have non-zero size');
        // backup-2 > backup-1: file_a вырос со 100 до 150 (+50)
        $this->assertGreaterThan($size1, $size2, 'Backup 2 should be larger than backup 1 (file_a grew)');
        // backup-3 > backup-2: добавился file_c на 50 байт
        $this->assertGreaterThan($size2, $size3, 'Backup 3 should be larger than backup 2 (new file added)');
    }

    public function testEachSnapshotContainsExpectedFiles(): void
    {
        foreach ($this->snapshots as $snapIndex => $snap) {
            $sid = $snap['short_id'];

            // Проверяем dir1
            $lsResult = $this->lsPath($snap['id'], '/dir1');
            foreach ($snap['dir1Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) /dir1 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in /dir1 should have size $expectedSize"
                );
            }

            // Проверяем dir2
            $lsResult = $this->lsPath($snap['id'], '/dir2');
            foreach ($snap['dir2Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) /dir2 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in /dir2 should have size $expectedSize"
                );
            }
        }
    }

    public function testFilesAreNotMixedBetweenSnapshots(): void
    {
        // backup-1: file_a.txt = 100 (оригинал), НЕ содержит file_c.txt
        $files1 = $this->lsPath($this->snapshots[0]['id'], '/dir1');
        $this->assertArrayHasKey('file_a.txt', $files1);
        $this->assertSame(100, $files1['file_a.txt'], 'backup-1 file_a should be 100 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files1, 'backup-1 should NOT contain file_c.txt');

        // backup-2: file_a.txt = 150 (изменён), НЕ содержит file_c.txt
        $files2 = $this->lsPath($this->snapshots[1]['id'], '/dir1');
        $this->assertArrayHasKey('file_a.txt', $files2);
        $this->assertSame(150, $files2['file_a.txt'], 'backup-2 file_a should be 150 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files2, 'backup-2 should NOT contain file_c.txt');

        // backup-3: file_a.txt = 150, file_c.txt = 50
        $files3 = $this->lsPath($this->snapshots[2]['id'], '/dir1');
        $this->assertArrayHasKey('file_a.txt', $files3);
        $this->assertSame(150, $files3['file_a.txt'], 'backup-3 file_a should be 150 bytes');
        $this->assertArrayHasKey('file_c.txt', $files3);
        $this->assertSame(50, $files3['file_c.txt'], 'backup-3 file_c should be 50 bytes');

        // file_b.txt одинаков во всех трёх (200 байт)
        foreach ([0, 1, 2] as $i) {
            $files = $this->lsPath($this->snapshots[$i]['id'], '/dir2');
            $this->assertArrayHasKey('file_b.txt', $files);
            $this->assertSame(200, $files['file_b.txt'], "backup-" . ($i + 1) . " file_b should be 200 bytes");
        }
    }

    public function testExportSingleFile(): void
    {
        // backup-3: file_a.txt = 150 bytes of 'X'
        $snapId = $this->snapshots[2]['id'];
        $result = $this->runner->run(
            ['restic', 'dump', $snapId, '/dir1/file_a.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'dump file_a.txt should succeed: ' . $result['stderr']);
        $this->assertEquals(str_repeat('X', 150), $result['stdout'], 'file_a.txt should contain exactly 150 X characters');
    }

    public function testExportEntireSnapshot(): void
    {
        $snapId = $this->snapshots[1]['id'];
        $result = $this->runner->run(
            ['restic', 'dump', $snapId, '/', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'dump / should succeed: ' . $result['stderr']);
        $this->assertGreaterThan(0, strlen($result['stdout']), 'tar output should not be empty');
        $this->assertStringContainsString('ustar', substr($result['stdout'], 257, 10), 'Output should be a valid tar archive');
    }

    public function testForgetKeepLastKeepsCorrectSnapshots(): void
    {
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '2', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(2, $snaps, 'should keep exactly 2 snapshots');

        $remainingIds = array_map(fn($s) => $s['id'] ?? '', $snaps);
        $this->assertContains($this->snapshots[1]['id'], $remainingIds, 'backup-2 should be kept');
        $this->assertContains($this->snapshots[2]['id'], $remainingIds, 'backup-3 should be kept');
        $this->assertNotContains($this->snapshots[0]['id'], $remainingIds, 'backup-1 should be removed');
    }

    public function testForgetThenCheck(): void
    {
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        $result = $this->runner->run(
            ['restic', 'check', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'check after forget should succeed: ' . $result['stderr']);
    }

    public function testUnlockSucceeds(): void
    {
        $result = $this->runner->run(
            ['restic', 'unlock', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'unlock on clean repo should succeed');
    }

    // ========================
    // Хелперы
    // ========================

    private function backup(string $tag): void
    {
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', '--tag', $tag, $this->dataDir],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], "Backup '$tag' should succeed: " . $result['stderr']);
    }

    /**
     * Выполняет restic ls --json для path внутри снепшота.
     * Возвращает массив [filename => size].
     *
     * @return array<string, int>
     */
    private function lsPath(string $snapId, string $path): array
    {
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $snapId, $path],
            ['RESTIC_PASSWORD' => '']
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
