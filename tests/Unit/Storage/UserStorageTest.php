<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Storage\ConfigStorage;
use App\Storage\UserStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест UserStorage (CRUD YAML-пользователей).
 *
 * Цель: проверить create/update/delete/updatePassword, валидацию имени
 *       (запрет '#'), защиту php-пользователей, roundtrip YAML и password_var.
 *
 * Сценарий:
 *   - create записывает пользователя в users.yaml.
 *   - create с недопустимым именем ('#') → InvalidArgumentException.
 *   - create дубликата → RuntimeException.
 *   - update yaml-пользователя → merge полей.
 *   - update/delete php-пользователя → RuntimeException (read-only).
 *   - updatePassword → хеш проверяется через password_verify.
 *
 * Критерий успеха: все assert проходят.
 */
class UserStorageTest extends TestCase
{
    /** @var string */
    private string $tmpDir;
    /** @var string */
    private string $configDir;
    /** @var ConfigStorage */
    private ConfigStorage $configStorage;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_users_test_' . uniqid();
        $this->configDir = $this->tmpDir . '/cfg';
        mkdir($this->configDir, 0777, true);
        mkdir($this->tmpDir . '/data', 0777, true);

        $this->configStorage = new ConfigStorage($this->configDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /** create записывает пользователя в users.yaml (roundtrip). */
    public function testCreateWritesUserToYaml(): void
    {
        $storage = new UserStorage($this->configStorage);
        $storage->create('viewer', [
            'password' => null,
            'password_var' => 'VIEWER_PASSWORD_HASH',
            'api_tokens' => [],
            'can_init' => false,
            'can_delete' => false,
            'repos' => ['public' => ['use' => true, 'use_read' => true, 'use_write' => false, 'edit' => false]],
        ]);

        $yaml = $this->configStorage->loadYamlUsers();
        $this->assertArrayHasKey('viewer', $yaml);
        $this->assertSame('VIEWER_PASSWORD_HASH', $yaml['viewer']['password_var']);
        $this->assertTrue($yaml['viewer']['repos']['public']['use']);
    }

    /** create с '#' в имени → InvalidArgumentException. */
    public function testCreateRejectsHashInUsername(): void
    {
        $storage = new UserStorage($this->configStorage);

        $this->expectException(\InvalidArgumentException::class);
        $storage->create('bad#name', ['password' => null]);
    }

    /** create существующего пользователя → RuntimeException. */
    public function testCreateRejectsDuplicate(): void
    {
        $storage = new UserStorage($this->configStorage);
        $storage->create('viewer', ['password' => null]);

        $this->expectException(\RuntimeException::class);
        $storage->create('viewer', ['password' => null]);
    }

    /** update yaml-пользователя мержит поля. */
    public function testUpdateMergesFields(): void
    {
        $storage = new UserStorage($this->configStorage);
        $storage->create('viewer', ['password' => null, 'can_init' => false]);

        $storage->update('viewer', ['can_init' => true, 'can_delete' => true]);

        $yaml = $this->configStorage->loadYamlUsers();
        $this->assertTrue($yaml['viewer']['can_init']);
        $this->assertTrue($yaml['viewer']['can_delete']);
        $this->assertNull($yaml['viewer']['password']);
    }

    /** update php-пользователя → RuntimeException (read-only). */
    public function testUpdateRejectsPhpUser(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return ["admin" => ["password" => "hash"]];'
        );

        $storage = new UserStorage($this->configStorage);

        $this->expectException(\RuntimeException::class);
        $storage->update('admin', ['can_init' => true]);
    }

    /** delete php-пользователя → RuntimeException (read-only). */
    public function testDeleteRejectsPhpUser(): void
    {
        file_put_contents(
            $this->configDir . '/users.php',
            '<?php return ["admin" => ["password" => "hash"]];'
        );

        $storage = new UserStorage($this->configStorage);

        $this->expectException(\RuntimeException::class);
        $storage->delete('admin');
    }

    /** delete yaml-пользователя удаляет его. */
    public function testDeleteRemovesYamlUser(): void
    {
        $storage = new UserStorage($this->configStorage);
        $storage->create('viewer', ['password' => null]);
        $storage->delete('viewer');

        $this->assertArrayNotHasKey('viewer', $this->configStorage->loadYamlUsers());
    }

    /** updatePassword сохраняет bcrypt-хеш, проверяемый password_verify. */
    public function testUpdatePasswordStoresHash(): void
    {
        $storage = new UserStorage($this->configStorage);
        $storage->create('viewer', ['password' => null]);

        $storage->updatePassword('viewer', password_hash('secret123', PASSWORD_DEFAULT));

        $yaml = $this->configStorage->loadYamlUsers();
        $this->assertNotSame('secret123', $yaml['viewer']['password']);
        $this->assertTrue(password_verify('secret123', $yaml['viewer']['password']));
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
