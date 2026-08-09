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
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries, 'Root should contain entries');

        // At least one directory should exist (we created /a/b/file.txt)
        $dirs = array_filter($entries, function ($e): bool {
            return is_array($e) && ($e['type'] ?? '') === 'dir';
        });
        $this->assertNotEmpty($dirs, 'Should find at least one directory at root');
    }

    public function testBrowseSubdirectory(): void
    {
        $dataDir = $this->tmpDir . '/data';
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $this->snapId, $dataDir . '/a/b'],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], 'Browse subdir should succeed: ' . $result['stderr']);
        $entries = $this->parseNdjson($result['stdout']);
        $this->assertIsArray($entries);

        $files = array_filter($entries, function ($e): bool {
            return is_array($e) && ($e['type'] ?? '') === 'file';
        });
        $this->assertNotEmpty($files, 'Should find at least one file in /a/b');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseNdjson(string $output): array
    {
        $entries = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'null') {
                continue;
            }
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        return $entries;
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
