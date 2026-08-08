<?php

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ResticConnectionTest extends TestCase
{
    private string $tmpDir;
    private string $repoDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_integration_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Initialize a restic repository without a password
        $runner = new CommandRunner();
        $result = $runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to initialize restic repository: ' . $result['stderr']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testConnectionToInitializedRepository(): void
    {
        // Create a temporary repositories.yaml
        $yamlFile = $this->tmpDir . '/repositories.yaml';
        $repos = [
            'repositories' => [
                [
                    'id' => 'test123',
                    'name' => 'Test Repo',
                    'type' => 'local',
                    'path' => $this->repoDir,
                    'password' => null,
                ],
            ],
        ];
        file_put_contents($yamlFile, Yaml::dump($repos));

        // Load via RepositoryStorage
        $storage = new RepositoryStorage($yamlFile);
        $loaded = $storage->loadAll();

        $this->assertCount(1, $loaded);
        $this->assertSame('test123', $loaded[0]['id']);

        // Test connection
        $service = new RepositoryService(new CommandRunner());
        $result = $service->testConnection($loaded[0]);

        $this->assertTrue($result['ok'], 'Connection should succeed: ' . ($result['error'] ?? ''));
        $this->assertJson($result['output']);

        // Output should be a JSON array (snapshots), possibly empty
        $snapshots = json_decode($result['output'], true);
        $this->assertIsArray($snapshots);
        $this->assertEmpty($snapshots, 'New repository should have no snapshots');
    }

    public function testConnectionFailsForNonExistentRepository(): void
    {
        $repository = [
            'id' => 'nonexistent',
            'name' => 'Nonexistent',
            'type' => 'local',
            'path' => '/nonexistent/path/to/repo',
            'password' => null,
        ];

        $service = new RepositoryService(new CommandRunner());
        $result = $service->testConnection($repository);

        $this->assertFalse($result['ok'], 'Connection to nonexistent repo should fail');
        $this->assertNotEmpty($result['error']);
    }

    public function testInitRepository(): void
    {
        $initDir = $this->tmpDir . '/init-repo';
        mkdir($initDir, 0777, true);

        $repository = [
            'path' => $initDir,
            'password' => null,
        ];

        $service = new RepositoryService(new CommandRunner());
        $result = $service->init($repository);

        $this->assertTrue($result['ok'], 'Init should succeed: ' . ($result['error'] ?? ''));

        // Verify connection works on newly initialized repo
        $connResult = $service->testConnection([
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => $initDir,
            'password' => null,
        ]);

        $this->assertTrue($connResult['ok'], 'Connection after init should succeed: ' . ($connResult['error'] ?? ''));
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
