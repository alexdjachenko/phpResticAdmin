<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест RepositoryService (init и testConnection через моки).
 *
 * Цель: проверить логику init (успех, ошибка, fallback при пустом stderr,
 *       передача пароля, insecure-флаг) и testConnection (ошибка).
 *
 * Сценарий:
 *   - init успешен: ok=true, error=''.
 *   - init ошибка: ok=false, error содержит stderr.
 *   - init ошибка с пустым stderr: ok=false, error не пустой (fallback).
 *   - init с паролем: RESTIC_PASSWORD в env, без --insecure-no-password.
 *   - init без пароля: --insecure-no-password в команде.
 *   - testConnection ошибка: ok=false, error не пустой.
 *
 * Критерий успеха: моки проверяют аргументы, сервис возвращает ожидаемые структуры.
 */
class RepositoryServiceTest extends TestCase
{
    /** init: успех → ok=true, error=''. */
    public function testInitReturnsOkTrueOnSuccess(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertTrue($result['ok']);
        $this->assertSame('', $result['error']);
    }

    /** init: ошибка → ok=false, error = stderr. */
    public function testInitReturnsStderrOnFailure(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Fatal: repository master key and config already initialized',
            ]);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/existing-repo', 'password' => null]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('already initialized', $result['error']);
    }

    /**
     * init: ошибка с пустым stderr → ok=false, но error не пустой (fallback).
     *
     * Симулирует случай, когда restic не в PATH или proc_open с array args
     * падает молча: stderr пуст, но exitCode != 0.
     */
    public function testInitReturnsFallbackErrorWhenStderrEmpty(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 127,
                'stdout' => '',
                'stderr' => '',
            ]);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error'], 'error should not be empty when init fails with empty stderr');
    }

    /** init с паролем: RESTIC_PASSWORD в env. */
    public function testInitPassesPasswordAsEnv(): void
    {
        $capturedEnv = null;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->anything(),
                $this->callback(function (array $env) use (&$capturedEnv) {
                    $capturedEnv = $env;
                    return true;
                })
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $service->init(['path' => '/tmp/test-repo', 'password' => 'secret123']);

        $this->assertNotNull($capturedEnv);
        $this->assertArrayHasKey('RESTIC_PASSWORD', $capturedEnv);
        $this->assertSame('secret123', $capturedEnv['RESTIC_PASSWORD']);
    }

    /** init без пароля: --insecure-no-password и --repo ДО init. */
    public function testInitAddsInsecureFlagWhenNoPassword(): void
    {
        $capturedCommand = null;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) use (&$capturedCommand) {
                    $capturedCommand = $cmd;
                    return true;
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertNotNull($capturedCommand);
        // Глобальные флаги должны идти ДО подкоманды init
        $cmdStr = implode(' ', $capturedCommand);
        $this->assertStringContainsString('--insecure-no-password', $cmdStr);
        $insecurePos = array_search('--insecure-no-password', $capturedCommand, true);
        $initPos = array_search('init', $capturedCommand, true);
        $this->assertIsInt($insecurePos);
        $this->assertIsInt($initPos);
        $this->assertLessThan($initPos, $insecurePos, '--insecure-no-password must come BEFORE init');

        $repoPos = array_search('--repo', $capturedCommand, true);
        $this->assertIsInt($repoPos);
        $this->assertLessThan($initPos, $repoPos, '--repo must come BEFORE init');
    }

    /** init с паролем: --insecure-no-password НЕ добавляется, --repo ДО init. */
    public function testInitDoesNotAddInsecureFlagWithPassword(): void
    {
        $capturedCommand = null;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) use (&$capturedCommand) {
                    $capturedCommand = $cmd;
                    return true;
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $service->init(['path' => '/tmp/test-repo', 'password' => 'secret']);

        $this->assertNotNull($capturedCommand);
        $this->assertNotContains('--insecure-no-password', $capturedCommand);

        // --repo должен идти ДО init
        $repoPos = array_search('--repo', $capturedCommand, true);
        $initPos = array_search('init', $capturedCommand, true);
        $this->assertIsInt($repoPos);
        $this->assertIsInt($initPos);
        $this->assertLessThan($initPos, $repoPos, '--repo must come BEFORE init');
    }

    /** testConnection: ошибка → ok=false, error не пустой. */
    public function testTestConnectionReturnsErrorOnFailure(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Is there a repository at this location?',
            ]);

        $service = new RepositoryService($mock);
        $result = $service->testConnection([
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => '/nonexistent',
            'password' => null,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * testConnection без пароля: --insecure-no-password и --repo ДО snapshots.
     */
    public function testTestConnectionCommandOrderWithoutPassword(): void
    {
        $capturedCommand = null;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) use (&$capturedCommand) {
                    $capturedCommand = $cmd;
                    return true;
                }),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '[]', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $service->testConnection([
            'id' => 'test',
            'name' => 'Test',
            'type' => 'local',
            'path' => '/tmp/repo',
            'password' => null,
        ]);

        $this->assertNotNull($capturedCommand);
        $insecurePos = array_search('--insecure-no-password', $capturedCommand, true);
        $repoPos = array_search('--repo', $capturedCommand, true);
        $snapPos = array_search('snapshots', $capturedCommand, true);
        $this->assertIsInt($insecurePos);
        $this->assertIsInt($repoPos);
        $this->assertIsInt($snapPos);
        $this->assertLessThan($snapPos, $insecurePos, '--insecure-no-password must come BEFORE snapshots');
        $this->assertLessThan($snapPos, $repoPos, '--repo must come BEFORE snapshots');
    }

    /**
     * init: когда и stderr, и stdout пусты при ошибке — fallback-сообщение.
     *
     * Симулирует случай, когда restic падает молча (например, не может
     * создать директорию из-за прав, но не выводит ошибку).
     */
    public function testInitReturnsFallbackErrorWhenBothStreamsEmpty(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => '',
            ]);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error'], 'error should not be empty when init fails silently');
        $this->assertStringContainsString('exited with code', $result['error'], 'Fallback error should mention exit code');
    }
    }
