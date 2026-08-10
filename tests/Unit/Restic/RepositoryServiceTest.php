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

class RepositoryServiceTest extends TestCase
{
    public function testInitReturnsOkTrueOnSuccess(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => 'repository initialized', 'stderr' => '']);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertTrue($result['ok']);
        $this->assertSame('', $result['error']);
    }

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

    public function testInitReturnsFallbackErrorWhenStderrEmpty(): void
    {
        // Simulates the case where proc_open with array args fails silently
        // (e.g., restic not in PATH) — stderr empty, exitCode non-zero.
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 127,
                'stdout' => '',
                'stderr' => '', // <-- the bug: empty stderr
            ]);

        $service = new RepositoryService($mock);
        $result = $service->init(['path' => '/tmp/test-repo', 'password' => null]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error'], 'error should not be empty when init fails with empty stderr');
    }

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
        $this->assertContains('--insecure-no-password', $capturedCommand);
    }

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
    }

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
}
