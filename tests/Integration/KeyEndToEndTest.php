<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class KeyEndToEndTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;
    private CommandRunner $runner;
    private string $repoPassword = 'testpass123';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_key_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->runner = new CommandRunner();

        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword]
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testListKeys(): void
    {
        $result = $this->runner->run(
            ['restic', 'key', 'list', '--json', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword]
        );

        $this->assertSame(0, $result['exitCode'], 'key list should succeed: ' . $result['stderr']);
        $keys = json_decode($result['stdout'], true);
        $this->assertIsArray($keys);
        $this->assertCount(1, $keys, 'should have exactly 1 key after init');
        $this->assertTrue($keys[0]['current'] ?? false, 'initial key should be current');
    }

    public function testAddAndRemoveKey(): void
    {
        // Add key
        $result = $this->runner->run(
            ['restic', 'key', 'add', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword],
            "newpass456\nnewpass456\n"
        );
        $this->assertSame(0, $result['exitCode'], 'key add should succeed: ' . $result['stderr']);

        // List keys - should have 2
        $listResult = $this->runner->run(
            ['restic', 'key', 'list', '--json', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword]
        );
        $keys = json_decode($listResult['stdout'], true);
        $this->assertIsArray($keys);
        $this->assertCount(2, $keys, 'should have 2 keys after adding');

        // Find the non-current key
        $newKeyId = null;
        foreach ($keys as $key) {
            if (empty($key['current'])) {
                $newKeyId = $key['id'];
                break;
            }
        }
        $this->assertNotNull($newKeyId, 'should find a non-current key');

        // Remove the new key
        $removeResult = $this->runner->run(
            ['restic', 'key', 'remove', $newKeyId, '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword]
        );
        $this->assertSame(0, $removeResult['exitCode'], 'key remove should succeed: ' . $removeResult['stderr']);

        // Should be back to 1 key
        $listResult = $this->runner->run(
            ['restic', 'key', 'list', '--json', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword]
        );
        $keysAfter = json_decode($listResult['stdout'], true);
        $this->assertIsArray($keysAfter);
        $this->assertCount(1, $keysAfter, 'should have 1 key after removal');
    }

    public function testChangePassword(): void
    {
        // Change password (restic 0.19+ key passwd no longer takes key ID)
        $result = $this->runner->run(
            ['restic', 'key', 'passwd', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => $this->repoPassword],
            "changed789\nchanged789\n"
        );
        $this->assertSame(0, $result['exitCode'], 'key passwd should succeed: ' . $result['stderr']);

        // Key should still exist
        $listResult = $this->runner->run(
            ['restic', 'key', 'list', '--json', '--repo', $this->repoDir],
            ['RESTIC_PASSWORD' => 'changed789']
        );
        $keysAfter = json_decode($listResult['stdout'], true);
        $this->assertIsArray($keysAfter);
        $this->assertCount(1, $keysAfter, 'key should still exist after password change');
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
