<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class ExportEndToEndTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private string $dataDir;
    private CommandRunner $runner;
    private string $snapId;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_export_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);
        mkdir($this->dataDir . '/dir', 0777, true);

        file_put_contents($this->dataDir . '/dir/file.txt', 'Hello Export');

        $this->runner = new CommandRunner();

        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $this->dataDir],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'Backup should succeed: ' . $result['stderr']);

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

    public function testDumpSingleFileReturnsCorrectContent(): void
    {
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, $this->dataDir . '/dir/file.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'dump should succeed: ' . $result['stderr']);
        $this->assertStringContainsString('Hello Export', $result['stdout']);
    }

    public function testDumpSnapshotReturnsTarArchive(): void
    {
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, '/', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'dump / should succeed: ' . $result['stderr']);
        $this->assertGreaterThan(0, strlen($result['stdout']), 'tar output should not be empty');

        // Check tar signature: "ustar" at position 257 in standard tar header
        $this->assertStringContainsString('ustar', substr($result['stdout'], 257, 10), 'Output should be a valid tar archive');
    }

    public function testDumpNonexistentFileFails(): void
    {
        $result = $this->runner->run(
            ['restic', 'dump', $this->snapId, '/nonexistent.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

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
