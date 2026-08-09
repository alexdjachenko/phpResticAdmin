<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use App\Restic\KeyService;
use PHPUnit\Framework\TestCase;

class KeyServiceTest extends TestCase
{
    /** @var array{id: string, name: string, type: string, path: string, password: ?string} */
    private array $repo;
    private string $repoPath;

    protected function setUp(): void
    {
        $this->repoPath = '/tmp/test-repo';
        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test Repo',
            'type' => 'local',
            'path' => $this->repoPath,
            'password' => null,
        ];
    }

    public function testListKeysParsesJson(): void
    {
        $json = '[{"id":"abc123","current":true,"userName":"host","created":"2025-01-01T00:00:00Z"}]';

        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => $json, 'stderr' => '']);

        $service = new KeyService($mock);
        $keys = $service->listKeys($this->repo);

        $this->assertCount(1, $keys);
        $this->assertSame('abc123', $keys[0]['id']);
        $this->assertTrue($keys[0]['current']);
    }

    public function testListKeysHandlesEmptyOutput(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new KeyService($mock);
        $keys = $service->listKeys($this->repo);

        $this->assertSame([], $keys);
    }

    public function testListKeysHandlesInvalidJson(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => 'not json', 'stderr' => '']);

        $service = new KeyService($mock);
        $keys = $service->listKeys($this->repo);

        $this->assertSame([], $keys);
    }

    public function testListKeysHandlesErrorExitCode(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 1, 'stdout' => '', 'stderr' => 'error']);

        $service = new KeyService($mock);
        $keys = $service->listKeys($this->repo);

        $this->assertSame([], $keys);
    }

    public function testAddKeySendsPasswordToStdin(): void
    {
        $capturedStdin = null;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (?string $stdin) use (&$capturedStdin) {
                    $capturedStdin = $stdin;
                    return true;
                })
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new KeyService($mock);
        $result = $service->addKey($this->repo, 'secret123');

        $this->assertTrue($result['ok']);
        $this->assertSame("secret123\nsecret123\n", $capturedStdin);
    }

    public function testRemoveKeyBuildsCorrectCommand(): void
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
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new KeyService($mock);
        $result = $service->removeKey($this->repo, 'abc123');

        $this->assertTrue($result['ok']);
        $this->assertNotNull($capturedCommand);
        $this->assertContains('remove', $capturedCommand);
        $this->assertContains('abc123', $capturedCommand);
        $this->assertContains($this->repoPath, $capturedCommand);
    }

    public function testChangePasswordSendsNewPasswordToStdin(): void
    {
        $capturedStdin = null;
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
                $this->callback(function (?string $stdin) use (&$capturedStdin) {
                    $capturedStdin = $stdin;
                    return true;
                })
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new KeyService($mock);
        $result = $service->changePassword($this->repo, 'abc123', 'newpass456');

        $this->assertTrue($result['ok']);
        $this->assertNotNull($capturedStdin);
        $this->assertSame("newpass456\nnewpass456\n", $capturedStdin);
        $this->assertContains('passwd', $capturedCommand);
        $this->assertContains('abc123', $capturedCommand);
    }
}
