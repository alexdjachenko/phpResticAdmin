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

class SnapshotServiceTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    /** @var array<string, mixed> */
    private array $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_snap_test_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Init repo
        $runner = new CommandRunner();
        $result = $runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        // Create a test file and backup
        $testDir = $this->tmpDir . '/data';
        mkdir($testDir, 0777, true);
        file_put_contents($testDir . '/test.txt', 'Hello World');

        $backupResult = $runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', $testDir],
            ['RESTIC_PASSWORD' => '']
        );

        if ($backupResult['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to create backup: ' . $backupResult['stderr']);
        }

        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testListSnapshots(): void
    {
        $service = new SnapshotService(new CommandRunner());
        $snapshots = $service->listSnapshots($this->repo);

        $this->assertIsArray($snapshots);
        $this->assertNotEmpty($snapshots, 'Should have at least one snapshot');

        $snap = $snapshots[0];
        $this->assertArrayHasKey('id', $snap);
        $this->assertArrayHasKey('short_id', $snap);
        $this->assertArrayHasKey('time', $snap);
        $this->assertArrayHasKey('paths', $snap);
        $this->assertArrayHasKey('summary', $snap);
        $this->assertArrayHasKey('total_bytes_processed', $snap['summary']);
    }

    public function testGetSnapshot(): void
    {
        $service = new SnapshotService(new CommandRunner());
        $snapshots = $service->listSnapshots($this->repo);

        $this->assertNotEmpty($snapshots);
        $shortId = $snapshots[0]['short_id'];

        $snap = $service->getSnapshot($this->repo, $shortId);
        $this->assertNotNull($snap);
        $this->assertSame($shortId, $snap['short_id']);
    }

    public function testAddAndRemoveTag(): void
    {
        $service = new SnapshotService(new CommandRunner());
        $snapshots = $service->listSnapshots($this->repo);

        $this->assertNotEmpty($snapshots);
        $snapId = $snapshots[0]['id'];
        $shortId = $snapshots[0]['short_id'] ?? '';

        // Add tag using short_id (restic tag prefers short IDs)
        $result = $service->addTag($this->repo, $shortId, 'test-tag-xyz');
        $this->assertTrue($result['ok'], 'Add tag should succeed: ' . ($result['error'] ?? ''));

        // Verify tag is present
        $snapshots = $service->listSnapshots($this->repo);
        $this->assertNotEmpty($snapshots, 'Should still have snapshots after tag add');
        $tags = $snapshots[0]['tags'] ?? [];
        $this->assertContains('test-tag-xyz', $tags, 'Tag should be present after add');

        // Remove tag using short_id
        $result = $service->removeTag($this->repo, $shortId, 'test-tag-xyz');
        $this->assertTrue($result['ok'], 'Remove tag should succeed: ' . ($result['error'] ?? ''));

        // Verify tag is removed - find snapshot by ID explicitly
        $snapshots = $service->listSnapshots($this->repo);
        $this->assertNotEmpty($snapshots, 'Should still have snapshots after tag remove');
        $found = null;
        foreach ($snapshots as $s) {
            if (($s['id'] ?? '') === $snapId || ($s['short_id'] ?? '') === $shortId) {
                $found = $s;
                break;
            }
        }
        $this->assertNotNull($found, 'Should find the snapshot after tag remove');
        $tags = $found['tags'] ?? [];
        $this->assertNotContains('test-tag-xyz', $tags, 'Tag should be removed');
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
