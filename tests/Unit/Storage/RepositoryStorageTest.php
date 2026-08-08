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
        $repos = $storage->loadAll('testuser');

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
        $path = $this->tmpDir . '/nonexistent.yaml';
        $storage = new RepositoryStorage($path);
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);

        // File must be auto-created with a template comment
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString('# repositories:', $content);
        $this->assertStringContainsString('#   - id:', $content);
    }

    public function testLoadAllReturnsEmptyArrayForEmptyYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    public function testLoadAllReturnsEmptyArrayForNonArrayYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '42');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    public function testSaveAndLoadByCategory(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        // Save a public repo
        $repo = [
            'id' => 'pub1',
            'name' => 'Public Repo',
            'type' => 'local',
            'path' => '/backups/pub',
            'password' => null,
        ];
        $storage->save('public', $repo, 'testuser');

        // Save a private repo
        $repo2 = [
            'id' => 'priv1',
            'name' => 'Private Repo',
            'type' => 'local',
            'path' => '/backups/priv',
            'password' => null,
        ];
        $storage->save('private', $repo2, 'testuser');

        // Load all
        $all = $storage->loadAll('testuser');
        $this->assertCount(2, $all);

        // Check categories
        $categories = [];
        foreach ($all as $r) {
            $categories[] = $r['category'];
        }
        $this->assertContains('public', $categories);
        $this->assertContains('private', $categories);
    }

    public function testDeleteRemovesFromCorrectStorage(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'del1', 'name' => 'To Delete', 'type' => 'local', 'path' => '/tmp/del', 'password' => null], 'testuser');
        $storage->save('public', ['id' => 'keep1', 'name' => 'Keep', 'type' => 'local', 'path' => '/tmp/keep', 'password' => null], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(2, $all);

        $storage->delete('public', 'del1', 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('keep1', $all[0]['id']);
    }

    public function testMoveTransfersBetweenCategories(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'move1', 'name' => 'Move Me', 'type' => 'local', 'path' => '/tmp/move', 'password' => null], 'testuser');

        // Move from public to private
        $storage->move('move1', 'public', 'private', 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('private', $all[0]['category']);
        $this->assertSame('move1', $all[0]['id']);
        $this->assertSame('Move Me', $all[0]['name']);
    }

    public function testSessionReposAreLoaded(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        // Manually set session repos
        $_SESSION['session_repos'] = [
            ['id' => 'sess1', 'name' => 'Session Repo', 'type' => 'local', 'path' => '/tmp/sess', 'password' => null],
        ];

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('session', $all[0]['category']);
        $this->assertSame('sess1', $all[0]['id']);

        // Clean up
        unset($_SESSION['session_repos']);
    }

    public function testSessionReposDeleted(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $_SESSION['session_repos'] = [
            ['id' => 'sess1', 'name' => 'S1', 'type' => 'local', 'path' => '/tmp/s1', 'password' => null],
            ['id' => 'sess2', 'name' => 'S2', 'type' => 'local', 'path' => '/tmp/s2', 'password' => null],
        ];

        $storage->delete('session', 'sess1', 'testuser');

        $repos = $storage->loadSession();
        $this->assertCount(1, $repos);
        $this->assertSame('sess2', $repos[0]['id']);

        unset($_SESSION['session_repos']);
    }

    public function testSaveSessionRepo(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('session', ['id' => 'newsess', 'name' => 'New', 'type' => 'local', 'path' => '/tmp/new', 'password' => null], 'testuser');

        $repos = $storage->loadSession();
        $this->assertCount(1, $repos);
        $this->assertSame('newsess', $repos[0]['id']);

        unset($_SESSION['session_repos']);
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
