<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Storage\UserBootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Юнит-тест UserBootstrap (автосоздание admin2).
 *
 * Цель: проверить, что первый вызов создаёт users.yaml с admin2 и возвращает
 *       пароль (который проходит password_verify), а второй вызов возвращает
 *       null и не меняет файл.
 *
 * Сценарий:
 *   1. ensureAdmin2() на отсутствующем файле → файл создан, пароль возвращён.
 *   2. ensureAdmin2() повторно → null, содержимое файла не изменилось.
 *
 * Критерий успеха: все assert проходят.
 */
class UserBootstrapTest extends TestCase
{
    /** @var string */
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_bootstrap_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /** Первый вызов создаёт admin2 и возвращает валидный пароль. */
    public function testFirstCallCreatesAdmin2(): void
    {
        $usersFile = $this->tmpDir . '/users.yaml';
        $bootstrap = new UserBootstrap();

        $password = $bootstrap->ensureAdmin2($usersFile);

        $this->assertNotNull($password, 'first call should return a generated password');
        $this->assertFileExists($usersFile);

        $users = Yaml::parseFile($usersFile);
        $this->assertIsArray($users);
        $this->assertArrayHasKey('admin2', $users);
        $this->assertTrue($users['admin2']['can_manage_users']);
        $this->assertTrue($users['admin2']['can_manage_processes']);
        $this->assertTrue(password_verify($password, $users['admin2']['password']));
    }

    /** Второй вызов возвращает null и не меняет файл. */
    public function testSecondCallReturnsNullAndKeepsFile(): void
    {
        $usersFile = $this->tmpDir . '/users.yaml';
        $bootstrap = new UserBootstrap();

        $firstPassword = $bootstrap->ensureAdmin2($usersFile);
        $contentAfterFirst = file_get_contents($usersFile);

        $secondPassword = $bootstrap->ensureAdmin2($usersFile);
        $contentAfterSecond = file_get_contents($usersFile);

        $this->assertNotNull($firstPassword);
        $this->assertNull($secondPassword, 'second call should not regenerate the user');
        $this->assertSame($contentAfterFirst, $contentAfterSecond);
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
