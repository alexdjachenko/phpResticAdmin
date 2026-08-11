<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Интеграционный тест соединения с restic-репозиторием.
 *
 * Цель: проверить работу RepositoryService при реальном взаимодействии
 *       с restic CLI: проверка соединения, инициализация репозитория,
 *       обработка ошибок (несуществующий репо, уже инициализированный,
 *       недоступный для записи родительский каталог).
 *
 * Сценарий:
 *   1. Инициализируется временный restic-репозиторий.
 *   2. Проверяется testConnection для валидного и невалидного репозитория.
 *   3. Проверяется init: успешный, повторный (должен упасть),
 *      с паролем, с недоступным родительским каталогом.
 *
 * Критерий успеха:
 *   - testConnection возвращает ok=true для существующего репо.
 *   - testConnection возвращает ok=false для несуществующего пути.
 *   - init возвращает ok=true и создаёт директорию.
 *   - init на уже инициализированном репо возвращает ok=false.
 *   - init с паролем: ok=true, testConnection с паролем подтверждает.
 *   - init с не-writable родителем возвращает ok=false.
 *
 * Требует: restic в PATH.
 */
class ResticConnectionTest extends TestCase
{
    /** @var string Временная директория для всего теста */
    private string $tmpDir;
    /** @var string Путь к основному тестовому restic-репозиторию */
    private string $repoDir;

    protected function setUp(): void
    {
        // Создаём изолированную временную директорию
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_integration_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Инициализируем restic-репозиторий без пароля для всех тестов класса
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

    /**
     * Проверяет успешное соединение с инициализированным репозиторием.
     *
     * Сценарий: создаётся YAML-конфиг репозитория, загружается через
     *            RepositoryStorage, затем testConnection проверяет связь.
     *            Ожидается: ok=true, output — валидный JSON (пустой массив).
     */
    public function testConnectionToInitializedRepository(): void
    {
        // Arrange: создаём временный repositories.yaml с одним репозиторием
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

        // Загружаем через RepositoryStorage (проверяем заодно парсинг YAML)
        $storage = new RepositoryStorage($yamlFile);
        $loaded = $storage->loadAll('test');

        $this->assertCount(1, $loaded);
        $this->assertSame('test123', $loaded[0]['id']);

        // Act: проверяем соединение с репозиторием
        $service = new RepositoryService(new CommandRunner());
        $result = $service->testConnection($loaded[0]);

        // Assert: соединение успешно
        $this->assertTrue($result['ok'], 'Connection should succeed: ' . ($result['error'] ?? ''));
        $this->assertJson($result['output']);

        // Assert: output — JSON-массив снепшотов (пустой для нового репо)
        $snapshots = json_decode($result['output'], true);
        $this->assertIsArray($snapshots);
        $this->assertEmpty($snapshots, 'New repository should have no snapshots');
    }

    /**
     * Проверяет, что testConnection возвращает ошибку для несуществующего пути.
     */
    public function testConnectionFailsForNonExistentRepository(): void
    {
        // Arrange: репозиторий с заведомо несуществующим путём
        $repository = [
            'id' => 'nonexistent',
            'name' => 'Nonexistent',
            'type' => 'local',
            'path' => '/nonexistent/path/to/repo',
            'password' => null,
        ];

        // Act
        $service = new RepositoryService(new CommandRunner());
        $result = $service->testConnection($repository);

        // Assert: ok=false, сообщение об ошибке не пустое
        $this->assertFalse($result['ok'], 'Connection to nonexistent repo should fail');
        $this->assertNotEmpty($result['error']);
    }

    /**
     * Проверяет успешную инициализацию нового репозитория.
     *
     * Важно: директория НЕ должна существовать до init — restic должен
     *        создать её сам. Предыдущая версия теста предсоздавала
     *        директорию, маскируя возможные ошибки init.
     */
    public function testInitRepository(): void
    {
        // Arrange: путь к ещё не существующей директории
        $initDir = $this->tmpDir . '/init-repo';

        $repository = [
            'path' => $initDir,
            'password' => null,
        ];

        // Act: инициализируем репозиторий
        $service = new RepositoryService(new CommandRunner());
        $result = $service->init($repository);

        // Assert: init успешен, директория создана
        $this->assertTrue($result['ok'], 'Init should succeed: ' . ($result['error'] ?? ''));
        $this->assertDirectoryExists($initDir, 'restic init should create the repository directory');

        // Дополнительно: проверяем, что testConnection работает после init
        $connResult = $service->testConnection([
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => $initDir,
            'password' => null,
        ]);

        $this->assertTrue($connResult['ok'], 'Connection after init should succeed: ' . ($connResult['error'] ?? ''));
    }

    /**
     * Проверяет, что повторный init на уже инициализированном репозитории
     * возвращает ошибку.
     */
    public function testInitRepositoryFailsForAlreadyInitializedRepo(): void
    {
        // Arrange: repoDir уже инициализирован в setUp()
        $repository = [
            'path' => $this->repoDir,
            'password' => null,
        ];

        // Act: пытаемся инициализировать повторно
        $service = new RepositoryService(new CommandRunner());
        $result = $service->init($repository);

        // Assert: ok=false, сообщение об ошибке содержит "config file already exists"
        $this->assertFalse($result['ok'], 'Init on already-initialized repo should fail');
        $this->assertNotEmpty($result['error'], 'Error message should not be empty');
        $this->assertStringContainsString('config file already exists', $result['error'], 'Error should mention "config file already exists"');
    }

    /**
     * Проверяет инициализацию репозитория с паролем.
     *
     * Сценарий: init с паролем, затем testConnection с тем же паролем.
     */
    public function testInitRepositoryWithPassword(): void
    {
        // Arrange: путь к новой директории
        $initDir = $this->tmpDir . '/init-password-repo';

        $repository = [
            'path' => $initDir,
            'password' => 'testSecret123',
        ];

        // Act: init с паролем
        $service = new RepositoryService(new CommandRunner());
        $result = $service->init($repository);

        // Assert: init успешен
        $this->assertTrue($result['ok'], 'Init with password should succeed: ' . ($result['error'] ?? ''));

        // Дополнительно: testConnection с тем же паролем должен работать
        $connResult = $service->testConnection([
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => $initDir,
            'password' => 'testSecret123',
        ]);

        $this->assertTrue($connResult['ok'], 'Connection after init with password should succeed: ' . ($connResult['error'] ?? ''));
    }

    /**
     * Проверяет, что init возвращает ошибку, если родительский каталог
     * недоступен для записи.
     */
    public function testInitRepositoryFailsForNonWritableParent(): void
    {
        // Arrange: создаём родительский каталог только для чтения
        $parentDir = $this->tmpDir . '/readonly-parent';
        mkdir($parentDir, 0555, true);

        $repository = [
            'path' => $parentDir . '/subdir-repo',
            'password' => null,
        ];

        // Act: пытаемся init в недоступную директорию
        $service = new RepositoryService(new CommandRunner());
        $result = $service->init($repository);

        // Assert: ok=false, ошибка не пустая
        $this->assertFalse($result['ok'], 'Init with non-writable parent should fail');
        $this->assertNotEmpty($result['error'], 'Error message should not be empty for permission failure');

        // Восстанавливаем права для tearDown
        chmod($parentDir, 0777);
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
