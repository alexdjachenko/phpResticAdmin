<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Process\TspClient;
use App\Process\TspTaskManager;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест TspTaskManager (метки, фильтрация и доступ).
 *
 * Цель: проверить генерацию label, фильтрацию задач по пользователю,
 *       доступ привилегированного (can_manage_processes) и assertAccess.
 *
 * Сценарий:
 *   - start() генерирует label в формате user#hex.
 *   - listForUser() фильтрует по префиксу <username>#.
 *   - privileged = true возвращает все задачи.
 *   - assertAccess пропускает только свои задачи (или все для privileged).
 *   - isValidLabel валидирует формат метки.
 *
 * Критерий успеха: мок TspClient проверяет логику менеджера.
 */
class TspTaskManagerTest extends TestCase
{
    /** start() генерирует label с префиксом username# и возвращает id. */
    public function testStartGeneratesLabelWithUsernamePrefix(): void
    {
        $capturedLabel = null;

        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())
            ->method('enqueue')
            ->willReturnCallback(function (string $label, array $command, array $env = []) use (&$capturedLabel): array {
                $capturedLabel = $label;
                return ['id' => 7, 'label' => $label];
            });

        $manager = new TspTaskManager($mock);
        $result = $manager->start('alice', ['echo', 'hi']);

        $this->assertSame(7, $result['id']);
        $this->assertNotNull($capturedLabel);
        $this->assertStringStartsWith('alice#', $capturedLabel);
        $this->assertSame($capturedLabel, $result['label']);
    }

    /** listForUser возвращает только задачи пользователя. */
    public function testListForUserFiltersByUsername(): void
    {
        $jobs = [
            ['id' => 1, 'state' => 'finished', 'command' => 'echo a', 'label' => 'alice#aaa', 'output' => null, 'errorlevel' => null],
            ['id' => 2, 'state' => 'finished', 'command' => 'echo b', 'label' => 'bob#bbb', 'output' => null, 'errorlevel' => null],
            ['id' => 3, 'state' => 'finished', 'command' => 'echo c', 'label' => null, 'output' => null, 'errorlevel' => null],
        ];

        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())
            ->method('list')
            ->willReturn($jobs);

        $manager = new TspTaskManager($mock);
        $result = $manager->listForUser('alice', false);

        $this->assertCount(1, $result);
        $this->assertSame('alice#aaa', $result[0]['label']);
    }

    /** privileged = true возвращает все задачи. */
    public function testListForUserPrivilegedReturnsAll(): void
    {
        $jobs = [
            ['id' => 1, 'state' => 'finished', 'command' => 'echo a', 'label' => 'alice#aaa', 'output' => null, 'errorlevel' => null],
            ['id' => 2, 'state' => 'finished', 'command' => 'echo b', 'label' => 'bob#bbb', 'output' => null, 'errorlevel' => null],
        ];

        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())
            ->method('list')
            ->willReturn($jobs);

        $manager = new TspTaskManager($mock);
        $result = $manager->listForUser('alice', true);

        $this->assertCount(2, $result);
    }

    /** assertAccess: своя задача — true, чужая — false, для privileged чужая — true. */
    public function testAssertAccessRules(): void
    {
        $manager = new TspTaskManager($this->createMock(TspClient::class));

        $this->assertTrue($manager->assertAccess('alice', 'alice#abc123', false));
        $this->assertFalse($manager->assertAccess('alice', 'bob#def456', false));
        $this->assertTrue($manager->assertAccess('alice', 'bob#def456', true));
        $this->assertFalse($manager->assertAccess('alice', 'invalid-label', false));
    }

    /** isValidLabel валидирует формат метки. */
    public function testIsValidLabel(): void
    {
        $manager = new TspTaskManager($this->createMock(TspClient::class));

        $this->assertTrue($manager->isValidLabel('alice#abc123'));
        $this->assertTrue($manager->isValidLabel('alice#0a9f'));
        $this->assertFalse($manager->isValidLabel('alice#XYZ'));
        $this->assertFalse($manager->isValidLabel('alice'));
        $this->assertFalse($manager->isValidLabel('alice#abc#def'));
        $this->assertFalse($manager->isValidLabel(''));
    }
}
