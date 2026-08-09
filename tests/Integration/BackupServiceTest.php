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

class BackupServiceTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_backup_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->runner = new CommandRunner();

        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testBackupCreatesSnapshot(): void
    {
        $dataDir = $this->tmpDir . '/data';
        mkdir($dataDir, 0777, true);
        file_put_contents($dataDir . '/hello.txt', 'Hello World');

        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dataDir],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

        $snapService = new SnapshotService($this->runner);
        $snapshots = $snapService->listSnapshots([
            'path' => $this->repoDir,
            'password' => null,
        ]);

        $this->assertNotEmpty($snapshots, 'Should have at least one snapshot after backup');
        $this->assertArrayHasKey('short_id', $snapshots[0]);
    }

    public function testBackupMultiplePaths(): void
    {
        $dir1 = $this->tmpDir . '/path1';
        $dir2 = $this->tmpDir . '/path2';
        mkdir($dir1, 0777, true);
        mkdir($dir2, 0777, true);
        file_put_contents($dir1 . '/a.txt', 'A');
        file_put_contents($dir2 . '/b.txt', 'B');

        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dir1, $dir2],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

        $snapService = new SnapshotService($this->runner);
        $snapshots = $snapService->listSnapshots([
            'path' => $this->repoDir,
            'password' => null,
        ]);

        $this->assertNotEmpty($snapshots);
        $paths = $snapshots[0]['paths'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($paths), 'Should contain both backup paths');
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
