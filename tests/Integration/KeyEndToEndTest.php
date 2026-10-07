<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Cache\RequestCache;
use App\Core\App;
use App\Restic\CommandRunner;
use App\Restic\KeyService;
use App\Restic\RepositoryService;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест управления ключами restic (реальный restic).
 *
 * Цель: проверить идентификацию ключа по паролю, безопасные удаление/смену и
 *       что рабочий ключ приложения не «переезжает» и не удаляется.
 *
 * Сценарий:
 *   1. init репозитория С паролем (repoPassword — рабочие реквизиты).
 *   2. identifyKey: ключ рабочего пароля помечен current; чужой пароль — свой ключ.
 *   3. workingKeyId указывает на рабочий ключ и НЕ меняется после identifyKey чужим паролем.
 *   4. Повторное добавление существующего пароля не создаёт ключ.
 *   5. Смена пароля дополнительного ключа: старый пароль этого ключа не работает,
 *      новый работает, рабочий ключ приложения продолжает работать.
 *   6. Удаление рабочего ключа по паролю блокируется (current_key).
 *
 * Критерий успеха:
 *   - рабочая идентификация и защита рабочего ключа;
 *   - дополнительный ключ меняется, рабочий ключ остаётся рабочим.
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
    /** @var string Пароль рабочего ключа (реквизиты) */
    private string $repoPassword = 'testpass123';

    protected function setUp(): void
    {
        // Изолируем кеш области запроса между тестами (в одном процессе).
        App::resetCaches();

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

        $repoService = new RepositoryService(new CommandRunner());
        $result = $repoService->init($this->repo);
        if (!$result['ok']) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['error']);
        }
    }

    protected function tearDown(): void
    {
        App::resetCaches();
        $this->removeDir($this->tmpDir);
    }

    /** Сервис с изолированным кешем запроса. */
    private function service(): KeyService
    {
        return new KeyService(new CommandRunner(), new RequestCache());
    }

    /** После init ровно 1 ключ, и он помечен current. */
    public function testListKeys(): void
    {
        $service = $this->service();
        $keys = $service->listKeys($this->repo);

        $this->assertCount(1, $keys, 'should have exactly 1 key after init');
        $this->assertTrue($keys[0]['current'] ?? false, 'initial key should be current');
    }

    /** Идентификация по паролю: рабочий пароль → рабочий ключ; рабочий ключ стабилен. */
    public function testIdentifyAndWorkingKey(): void
    {
        $service = $this->service();

        $working = $service->workingKeyId($this->repo);
        $this->assertNotNull($working, 'working key must be identified by repo credentials');

        $identified = $service->identifyKey($this->repo, $this->repoPassword);
        $this->assertNotNull($identified);
        $this->assertSame($working, $identified['id']);

        // Добавляем дополнительный ключ и проверяем, что бейдж (workingKeyId) не «переезжает».
        $add = $service->addKey($this->repo, 'extraPass1');
        $this->assertTrue($add['ok'], 'key add should succeed: ' . $add['error']);
        $this->assertNotSame($working, $add['key_id'], 'new key must differ from the working key');

        $this->assertSame($working, $service->workingKeyId($this->repo), 'working key must not move');

        // identifyKey чужим паролем не влияет на рабочий ключ.
        $extraIdentified = $service->identifyKey($this->repo, 'extraPass1');
        $this->assertNotNull($extraIdentified);
        $this->assertSame($add['key_id'], $extraIdentified['id']);
    }

    /** Повторное добавление существующего пароля не создаёт ключ. */
    public function testAddExistingPasswordIsRejected(): void
    {
        $service = $this->service();

        $result = $service->addKey($this->repo, $this->repoPassword);

        $this->assertFalse($result['ok']);
        $this->assertSame('duplicate', $result['error_code']);
        $this->assertCount(1, $service->listKeys($this->repo), 'no new key must be created');
    }

    /** Рабочий ключ нельзя удалить по паролю. */
    public function testRemoveWorkingKeyIsBlocked(): void
    {
        $service = $this->service();

        $result = $service->removeKeyByPassword($this->repo, $this->repoPassword);

        $this->assertFalse($result['ok']);
        $this->assertSame('current_key', $result['error_code']);
        $this->assertCount(1, $service->listKeys($this->repo), 'working key must survive');
    }

    /** Удаление дополнительного ключа по паролю работает. */
    public function testRemoveExtraKeyByPassword(): void
    {
        $service = $this->service();
        $add = $service->addKey($this->repo, 'extraPass2');
        $this->assertTrue($add['ok']);

        $result = $service->removeKeyByPassword($this->repo, 'extraPass2');
        $this->assertTrue($result['ok'], 'extra key removal should succeed: ' . $result['error']);

        $this->assertCount(1, $service->listKeys($this->repo), 'extra key must be removed');
    }

    /**
     * Смена пароля ДОПОЛНИТЕЛЬНОГО ключа: старый пароль этого ключа не работает,
     * новый работает, рабочий ключ приложения продолжает работать.
     */
    public function testChangeExtraKeyPassword(): void
    {
        $service = $this->service();
        $add = $service->addKey($this->repo, 'extraOld1');
        $this->assertTrue($add['ok']);

        $result = $service->changePassword($this->repo, 'extraOld1', 'extraNew1');
        $this->assertTrue($result['ok'], 'extra key passwd should succeed: ' . $result['error']);

        // Старый пароль дополнительного ключа больше не работает.
        $this->assertNull($service->identifyKey($this->repo, 'extraOld1'), 'old extra password must stop working');
        // Новый пароль работает.
        $this->assertNotNull($service->identifyKey($this->repo, 'extraNew1'), 'new extra password must work');
        // Рабочий ключ приложения продолжает работать.
        $this->assertNotNull($service->workingKeyId($this->repo), 'working key must keep working');
    }

    /** Смена пароля РАБОЧЕГО ключа: с новым паролем репозиторий доступен. */
    public function testChangeWorkingKeyPassword(): void
    {
        $service = $this->service();

        $result = $service->changePassword($this->repo, $this->repoPassword, 'workingNew1');
        $this->assertTrue($result['ok'], 'working key passwd should succeed: ' . $result['error']);

        $repoWithNew = array_merge($this->repo, ['password' => 'workingNew1']);
        $keysAfter = $service->listKeys($repoWithNew);
        $this->assertCount(1, $keysAfter, 'repository must remain accessible with the new password');
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
