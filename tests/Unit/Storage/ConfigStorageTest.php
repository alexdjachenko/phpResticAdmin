<?php

namespace App\Tests\Unit\Storage;

use App\Storage\ConfigStorage;
use PHPUnit\Framework\TestCase;

class ConfigStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testLoadUsersReturnsArray(): void
    {
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return ["admin" => ["password" => "hash123"]];'
        );

        $storage = new ConfigStorage($this->tmpDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertArrayHasKey('admin', $users);
        $this->assertSame('hash123', $users['admin']['password']);
    }

    public function testLoadUsersReturnsEmptyArrayWhenFileMissing(): void
    {
        $storage = new ConfigStorage($this->tmpDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertEmpty($users);
    }

    public function testLoadSettingsReturnsArray(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => null, "timezone" => "UTC"];'
        );

        $storage = new ConfigStorage($this->tmpDir);
        $settings = $storage->loadSettings();

        $this->assertIsArray($settings);
        $this->assertNull($settings['guest_user']);
        $this->assertSame('UTC', $settings['timezone']);
    }

    public function testLoadSettingsReturnsEmptyArrayWhenFileMissing(): void
    {
        $storage = new ConfigStorage($this->tmpDir);
        $settings = $storage->loadSettings();

        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    public function testLoadPhpFileReturnsEmptyArrayWhenFileReturnsNonArray(): void
    {
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return null;'
        );

        $storage = new ConfigStorage($this->tmpDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertEmpty($users);
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
