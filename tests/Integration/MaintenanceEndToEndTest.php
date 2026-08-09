<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class MaintenanceEndToEndTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private string $dataDir;
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_maint_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);
        mkdir($this->dataDir . '/a', 0777, true);
        mkdir($this->dataDir . '/b', 0777, true);
        mkdir($this->dataDir . '/c', 0777, true);

        $this->runner = new CommandRunner();

        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        // 3 backups with different tags
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

    public function testCheckRunsSuccessfully(): void
    {
        $result = $this->runner->run(
            ['restic', 'check', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'check should succeed: ' . $result['stderr']);
    }

    public function testForgetDryRunDoesNotDelete(): void
    {
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $count = count($snaps);

        $result = $this->runner->run(
            ['restic', 'forget', '--dry-run', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget dry-run should succeed: ' . $result['stderr']);

        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snapsAfter = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snapsAfter);
        $this->assertSame($count, count($snapsAfter), 'dry-run should not delete snapshots');
    }

    public function testForgetWithKeepLast(): void
    {
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(1, $snaps, 'forget --keep-last 1 should leave 1 snapshot');
    }

    public function testUnlockOnCleanRepoSucceeds(): void
    {
        $result = $this->runner->run(
            ['restic', 'unlock', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'unlock on clean repo should succeed');
    }

    public function testRebuildIndexSucceeds(): void
    {
        $result = $this->runner->run(
            ['restic', 'rebuild-index', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

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
