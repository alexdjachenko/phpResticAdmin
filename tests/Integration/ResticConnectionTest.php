<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест инициализации и соединения с restic-репозиторием.
 *
 * Каждый тест — цепочка: создание репозитория → работа с ним → негативные
 * сценарии на том же репозитории.
 *
 * Цепочка 1 (без пароля):
 *   init → testConnection успех (restic cat config) → повторный init провал.
 *
 * Цепочка 2 (с паролем):
 *   init → testConnection с правильным паролем успех →
 *   testConnection с неправильным паролем провал →
 *   testConnection без пароля провал.
 *
 * Цепочка 3 (ошибки без репозитория):
 *   init в не-writable родитель → провал →
 *   testConnection к несуществующему пути → провал.
 *
 * Критерий успеха: ok-сценарии возвращают ok=true, fail-сценарии — ok=false
 * с непустым error.
 *
 * Требует: restic в PATH.
 */
class ResticConnectionTest extends TestCase
{
    /** @var string Временная директория для изоляции тестов */
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_integration_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // === Цепочка 1: репозиторий без пароля ===

    /**
     * Цепочка с репозиторием без пароля.
     *
     * 1. init без пароля → ok, директория создана.
     * 2. testConnection без пароля → ok, output — текст конфига репозитория.
     * 3. Повторный init на том же репо → ok=false, "config file already exists".
     */
    public function testInitConnectionAndReinitWithoutPassword(): void
    {
        $repoDir = $this->tmpDir . '/repo-no-password';
        $service = new RepositoryService(new CommandRunner());
        $repo = [
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => $repoDir,
            'password' => null,
        ];

        // --- Шаг 1: init без пароля ---
        $result = $service->init(['path' => $repoDir, 'password' => null]);

        $this->assertTrue($result['ok'], 'Init should succeed: ' . ($result['error'] ?? ''));
        $this->assertDirectoryExists($repoDir, 'restic init should create the repository directory');

        // --- Шаг 2: testConnection (restic cat config) ---
        $connResult = $service->testConnection($repo);

        $this->assertTrue($connResult['ok'], 'Connection should succeed: ' . ($connResult['error'] ?? ''));
        $this->assertNotEmpty($connResult['output'], 'cat config should return repository config text');
        $this->assertStringContainsString('id', $connResult['output'], 'config should contain the repository id');

        // --- Шаг 3: повторный init → провал ---
        $reInitResult = $service->init(['path' => $repoDir, 'password' => null]);

        $this->assertFalse($reInitResult['ok'], 'Init on already-initialized repo should fail');
        $this->assertNotEmpty($reInitResult['error'], 'Error message should not be empty');
        $this->assertStringContainsString('config file already exists', $reInitResult['error'], 'Error should mention "config file already exists"');
    }

    // === Цепочка 2: репозиторий с паролем ===

    /**
     * Цепочка с репозиторием, защищённым паролем.
     *
     * 1. init с паролем → ok.
     * 2. testConnection с правильным паролем → ok.
     * 3. testConnection с неправильным паролем → ok=false.
     * 4. testConnection без пароля (через --insecure-no-password) → ok=false.
     */
    public function testInitAndConnectionWithPassword(): void
    {
        $repoDir = $this->tmpDir . '/repo-with-password';
        $password = 'testSecret123';
        $service = new RepositoryService(new CommandRunner());

        // --- Шаг 1: init с паролем ---
        $result = $service->init(['path' => $repoDir, 'password' => $password]);

        $this->assertTrue($result['ok'], 'Init with password should succeed: ' . ($result['error'] ?? ''));

        $repo = [
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => $repoDir,
            'password' => $password,
        ];

        // --- Шаг 2: testConnection с правильным паролем → успех ---
        $connOk = $service->testConnection($repo);

        $this->assertTrue($connOk['ok'], 'Connection with correct password should succeed: ' . ($connOk['error'] ?? ''));
        $this->assertNotEmpty($connOk['output'], 'cat config should return repository config text');

        // --- Шаг 3: testConnection с неправильным паролем → провал ---
        $repoWrongPw = array_merge($repo, ['password' => 'wrongPassword']);
        $connWrongPw = $service->testConnection($repoWrongPw);

        $this->assertFalse($connWrongPw['ok'], 'Connection with wrong password should fail');
        $this->assertNotEmpty($connWrongPw['error'], 'Error message should not be empty for wrong password');

        // --- Шаг 4: testConnection без пароля → провал ---
        $repoNoPw = array_merge($repo, ['password' => null]);
        $connNoPw = $service->testConnection($repoNoPw);

        $this->assertFalse($connNoPw['ok'], 'Connection without password to protected repo should fail');
        $this->assertNotEmpty($connNoPw['error'], 'Error message should not be empty when password is missing');
    }

    // === Негативные сценарии без существующего репозитория ===

    /**
     * Цепочка ошибок без существующего репозитория.
     *
     * 1. init в родительский каталог без прав на запись → ok=false.
     * 2. testConnection к заведомо несуществующему пути → ok=false.
     */
    public function testInitAndConnectionFailuresWithoutRepo(): void
    {
        $service = new RepositoryService(new CommandRunner());

        // --- Шаг 1: init в не-writable родитель ---
        $parentDir = $this->tmpDir . '/readonly-parent';
        mkdir($parentDir, 0555, true);

        $initResult = $service->init([
            'path' => $parentDir . '/subdir-repo',
            'password' => null,
        ]);

        $this->assertFalse($initResult['ok'], 'Init with non-writable parent should fail');
        $this->assertNotEmpty($initResult['error'], 'Error message should not be empty for permission failure');

        chmod($parentDir, 0777);

        // --- Шаг 2: testConnection к несуществующему пути ---
        $connResult = $service->testConnection([
            'id' => 'nonexistent',
            'name' => 'Nonexistent',
            'type' => 'local',
            'path' => '/nonexistent/path/to/repo',
            'password' => null,
        ]);

        $this->assertFalse($connResult['ok'], 'Connection to nonexistent repo should fail');
        $this->assertNotEmpty($connResult['error'], 'Error message should not be empty for nonexistent path');
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
