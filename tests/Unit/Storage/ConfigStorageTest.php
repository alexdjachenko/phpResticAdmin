<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Storage\ConfigStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест ConfigStorage (загрузка users.php, users.yaml и settings.php).
 *
 * Цель: проверить парсинг PHP-конфигов, обработку отсутствующих файлов
 *       и невалидного содержимого, а также поддержку users.yaml
 *       (дополнение к users.php с приоритетом users.php).
 *
 * Сценарий:
 *   - Загрузка существующего users.php и settings.php.
 *   - Отсутствующий файл → пустой массив.
 *   - Файл возвращает не-массив → пустой массив (защита).
 *   - Новый формат users.php с секцией repos.
 *   - users.yaml грузится, когда users.php отсутствует.
 *   - При совпадении логинов побеждает users.php.
 *   - Отсутствующий/невалидный yaml → только php.
 *
 * Критерий успеха: assertSame/assertArrayHasKey проходят.
 */
class ConfigStorageTest extends TestCase
{
    /** @var string Временная директория для конфигов */
    private string $tmpDir;
    /** @var string Временная директория PHP-конфигов (cfg) */
    private string $configDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_test_' . uniqid();
        $this->configDir = $this->tmpDir . '/cfg';
        mkdir($this->configDir, 0777, true);
        // Каталог для users.yaml (dirname(configDir) . '/data')
        mkdir($this->tmpDir . '/data', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /** Загрузка существующего users.php. */
    public function testLoadUsersReturnsArray(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return ["admin" => ["password" => "hash123"]];'
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertArrayHasKey('admin', $users);
        $this->assertSame('hash123', $users['admin']['password']);
    }

    /** Отсутствующий файл → пустой массив. */
    public function testLoadUsersReturnsEmptyArrayWhenFileMissing(): void
    {
        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertEmpty($users);
    }

    /** Загрузка settings.php. */
    public function testLoadSettingsReturnsArray(): void
    {
        file_put_contents(
            $this->configDir . '/settings.php',
            '<?php return ["guest_user" => null, "timezone" => "UTC"];'
        );

        $storage = new ConfigStorage($this->configDir);
        $settings = $storage->loadSettings();

        $this->assertIsArray($settings);
        $this->assertNull($settings['guest_user']);
        $this->assertSame('UTC', $settings['timezone']);
    }

    /** Отсутствующий settings.php → пустой массив. */
    public function testLoadSettingsReturnsEmptyArrayWhenFileMissing(): void
    {
        $storage = new ConfigStorage($this->configDir);
        $settings = $storage->loadSettings();

        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    /** Файл возвращает не-массив (null) → защита: пустой массив. */
    public function testLoadPhpFileReturnsEmptyArrayWhenFileReturnsNonArray(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return null;'
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertEmpty($users);
    }

    /** Загрузка users.php в новом формате (с секциями repos). */
    public function testLoadUsersWithNewFormat(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return [
                "admin" => [
                    "password" => "hash123",
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["use" => true, "edit" => true],
                        "private" => ["use" => true, "edit" => true],
                        "session" => ["use" => true, "edit" => true],
                    ],
                ],
                "guest" => [
                    "password" => null,
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["use" => true, "edit" => false],
                        "private" => ["use" => false, "edit" => false],
                        "session" => ["use" => false, "edit" => false],
                    ],
                ],
            ];'
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertArrayHasKey('admin', $users);
        $this->assertArrayHasKey('guest', $users);
        $this->assertSame('hash123', $users['admin']['password']);
        $this->assertTrue($users['admin']['repos']['public']['edit']);
        $this->assertNull($users['guest']['password']);
        $this->assertFalse($users['guest']['repos']['private']['use']);
    }

    /** users.yaml грузится, когда users.php отсутствует. */
    public function testLoadUsersYamlWhenPhpMissing(): void
    {
        file_put_contents(
            $this->tmpDir . '/data/users.yaml',
            "yamluser:\n    password: null\n    api_tokens: []\n    can_init: false\n    can_delete: false\n    repos:\n        public: { use: true, use_read: true, use_write: false, edit: false }\n"
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertIsArray($users);
        $this->assertArrayHasKey('yamluser', $users);
        $this->assertNull($users['yamluser']['password']);
        $this->assertFalse($users['yamluser']['repos']['public']['use_write']);
    }

    /** При совпадении логинов побеждает users.php. */
    public function testLoadUsersPhpWinsOverYaml(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return ["shared" => ["password" => "from-php"]];'
        );
        file_put_contents(
            $this->tmpDir . '/data/users.yaml',
            "shared:\n    password: from-yaml\n"
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertSame('from-php', $users['shared']['password']);
    }

    /** Отсутствующий/невалидный yaml → только php. */
    public function testLoadUsersInvalidYamlReturnsOnlyPhp(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return ["admin" => ["password" => "hash123"]];'
        );
        file_put_contents(
            $this->tmpDir . '/data/users.yaml',
            "bad: [unclosed"
        );

        $storage = new ConfigStorage($this->configDir);
        $users = $storage->loadUsers();

        $this->assertCount(1, $users);
        $this->assertArrayHasKey('admin', $users);
        $this->assertArrayNotHasKey('bad', $users);
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
