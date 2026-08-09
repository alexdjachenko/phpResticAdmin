<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class BrowseIntegrationTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_browse_test_' . uniqid();
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

        // Create nested structure: /a/b/file.txt
        $dataDir = $this->tmpDir . '/data';
        $subDir = $dataDir . '/a/b';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/file.txt', 'content');

        $backupResult = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $dataDir],
            ['RESTIC_PASSWORD' => '']
        );

        if ($backupResult['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to create backup: ' . $backupResult['stderr']);
        }

        // Get snapshot ID
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password', '--last'],
            ['RESTIC_PASSWORD' => '']
        );
        $snapshots = json_decode($snapResult['stdout'], true) ?: [];
        $this->snapId = $snapshots[0]['id'] ?? '';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private string $snapId;

    public function testBrowseRoot(): void
    {
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $this->snapId, '/'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Browse should succeed: ' . $result['stderr']);
        $entries = json_decode($result['stdout'], true);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries);

        // Find the 'a' directory
        $dirs = array_filter($entries, function (array $e): bool {
            return ($e['type'] ?? '') === 'dir' && ($e['name'] ?? '') === 'a';
        });
        $this->assertNotEmpty($dirs, 'Should find directory "a" at root');
    }

    public function testBrowseSubdirectory(): void
    {
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $this->snapId, '/a/b'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Browse subdir should succeed: ' . $result['stderr']);
        $entries = json_decode($result['stdout'], true);
        $this->assertIsArray($entries);

        $files = array_filter($entries, function (array $e): bool {
            return ($e['type'] ?? '') === 'file' && ($e['name'] ?? '') === 'file.txt';
        });
        $this->assertNotEmpty($files, 'Should find file.txt in /a/b');
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
