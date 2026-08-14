<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use App\Restic\KeyService;
use App\Restic\RepositoryService;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест управления ключами restic (key list/add/remove/passwd).
 *
 * Цель: проверить операции с ключами доступа к репозиторию через KeyService:
 *       просмотр списка, добавление, удаление, смена пароля.
 *
 * Сценарий:
 *   1. Инициализируется репозиторий С паролем (нужен для операций с ключами).
 *   2. key list — проверяется, что после init есть ровно 1 текущий ключ.
 *   3. key add — добавляется новый ключ, проверяется что стало 2.
 *   4. key remove — удаляется добавленный ключ, проверяется что остался 1.
 *   5. key passwd — меняется пароль, проверяется что ключ доступен с новым.
 *
 * Критерий успеха:
 *   - key list возвращает валидный JSON с 1 ключом (current=true).
 *   - После add — 2 ключа.
 *   - После remove — снова 1 ключ.
 *   - После passwd — ключ доступен с новым паролем.
 *
 * Требует: restic в PATH.
 */
class KeyEndToEndTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var array<string, mixed> Конфигурация тестового репозитория */
    private array $repo;
    /** @var string Пароль репозитория (нужен для key-операций) */
    private string $repoPassword = 'testpass123';

    protected function setUp(): void
    {
        // Создаём изолированную временную директорию
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_key_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        $this->repo = [
            'id' => 'key-repo',
            'name' => 'Key Repo',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => $this->repoPassword,
        ];

        // Инициализируем репозиторий С ПАРОЛЕМ (операции с ключами без пароля бессмысленны)
        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->init($this->repo);
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Проверяет, что после init в репозитории ровно 1 ключ, и он текущий.
     */
    public function testListKeys(): void
    {
        $service = new KeyService(new CommandRunner());
        $keys = $service->listKeys($this->repo);

        $this->assertCount(1, $keys, 'should have exactly 1 key after init');
        $this->assertTrue($keys[0]['current'] ?? false, 'initial key should be current');
    }

    /**
     * Проверяет полный цикл: add key → list (2 ключа) → remove → list (1 ключ).
     */
    public function testAddAndRemoveKey(): void
    {
        $service = new KeyService(new CommandRunner());

        // Шаг 1: добавляем новый ключ
        $result = $service->addKey($this->repo, 'newpass456');
        $this->assertTrue($result['ok'], 'key add should succeed: ' . $result['error']);

        // Шаг 2: проверяем, что теперь 2 ключа
        $keys = $service->listKeys($this->repo);
        $this->assertCount(2, $keys, 'should have 2 keys after adding');

        // Находим НЕ текущий ключ для удаления
        $newKeyId = null;
        foreach ($keys as $key) {
            if (empty($key['current'])) {
                $newKeyId = $key['id'];
                break;
            }
        }
        $this->assertNotNull($newKeyId, 'should find a non-current key');

        // Шаг 3: удаляем добавленный ключ
        $removeResult = $service->removeKey($this->repo, $newKeyId);
        $this->assertTrue($removeResult['ok'], 'key remove should succeed: ' . $removeResult['error']);

        // Шаг 4: проверяем, что снова 1 ключ
        $keysAfter = $service->listKeys($this->repo);
        $this->assertCount(1, $keysAfter, 'should have 1 key after removal');
    }

    /**
     * Проверяет смену пароля репозитория и доступ с новым паролем.
     *
     * Примечание: restic 0.19+ key passwd больше не принимает ID ключа.
     */
    public function testChangePassword(): void
    {
        $service = new KeyService(new CommandRunner());

        $result = $service->changePassword($this->repo, 'changed789');
        $this->assertTrue($result['ok'], 'key passwd should succeed: ' . $result['error']);

        // Assert: ключ доступен с НОВЫМ паролем
        $repoWithNewPassword = array_merge($this->repo, ['password' => 'changed789']);
        $keysAfter = $service->listKeys($repoWithNewPassword);
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
