<?php

namespace App\Tests\Unit\Storage;

use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;

class RepositoryStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_repo_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testLoadAllParsesYaml(): void
    {
        $yaml = <<<YAML
repositories:
    - id: "abc123"
      name: "Test Backup"
      type: "local"
      path: "/backups/test"
      password: null
    - id: "def456"
      name: "Remote Backup"
      type: "sftp"
      path: "sftp:host:/backup"
      password: "secret"
YAML;
        file_put_contents($this->tmpDir . '/repositories.yaml', $yaml);

        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll();

        $this->assertCount(2, $repos);
        $this->assertSame('abc123', $repos[0]['id']);
        $this->assertSame('Test Backup', $repos[0]['name']);
        $this->assertSame('local', $repos[0]['type']);
        $this->assertNull($repos[0]['password']);
        $this->assertSame('def456', $repos[1]['id']);
        $this->assertSame('secret', $repos[1]['password']);
    }

    public function testLoadAllReturnsEmptyArrayWhenFileMissing(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/nonexistent.yaml');
        $repos = $storage->loadAll();

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    public function testLoadAllReturnsEmptyArrayForEmptyYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll();

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    public function testLoadAllReturnsEmptyArrayForNonArrayYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '42');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll();

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
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
