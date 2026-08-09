<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use App\Restic\MaintenanceService;
use PHPUnit\Framework\TestCase;

class MaintenanceServiceTest extends TestCase
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

    public function testCheckCallsResticCheck(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) {
                    return in_array('check', $cmd, true) && in_array($this->repoPath, $cmd, true);
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => 'no errors', 'stderr' => '']);

        $service = new MaintenanceService($mock);
        $result = $service->check($this->repo);

        $this->assertTrue($result['ok']);
    }

    public function testPruneCallsResticPrune(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) {
                    return in_array('prune', $cmd, true) && in_array($this->repoPath, $cmd, true);
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new MaintenanceService($mock);
        $result = $service->prune($this->repo);

        $this->assertTrue($result['ok']);
    }

    public function testRebuildIndexCallsResticRebuildIndex(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) {
                    return in_array('rebuild-index', $cmd, true) && in_array($this->repoPath, $cmd, true);
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new MaintenanceService($mock);
        $result = $service->rebuildIndex($this->repo);

        $this->assertTrue($result['ok']);
    }

    public function testUnlockCallsResticUnlock(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (array $cmd) {
                    return in_array('unlock', $cmd, true) && in_array($this->repoPath, $cmd, true);
                }),
                $this->anything()
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new MaintenanceService($mock);
        $result = $service->unlock($this->repo);

        $this->assertTrue($result['ok']);
    }

    public function testForgetBuildsCorrectCommand(): void
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

        $policy = [
            'keep_daily' => 7,
            'keep_weekly' => 4,
            'keep_last' => 0,
            'prune' => true,
            'dry_run' => true,
        ];

        $service = new MaintenanceService($mock);
        $result = $service->forget($this->repo, $policy);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($capturedCommand);

        $cmdStr = implode(' ', $capturedCommand);
        $this->assertStringContainsString('--keep-daily 7', $cmdStr);
        $this->assertStringContainsString('--keep-weekly 4', $cmdStr);
        $this->assertStringContainsString('--prune', $cmdStr);
        $this->assertStringContainsString('--dry-run', $cmdStr);
        $this->assertStringNotContainsString('--keep-last', $cmdStr, 'keep_last=0 should not add --keep-last');
    }

    public function testForgetWithPasswordUsesEnv(): void
    {
        $repoWithPassword = array_merge($this->repo, ['password' => 'secret123']);

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

        $service = new MaintenanceService($mock);
        $service->forget($repoWithPassword, ['keep_last' => 5]);

        $this->assertNotNull($capturedEnv);
        $this->assertArrayHasKey('RESTIC_PASSWORD', $capturedEnv);
        $this->assertSame('secret123', $capturedEnv['RESTIC_PASSWORD']);
    }

    public function testForgetWithoutPasswordUsesInsecureFlag(): void
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
                $this->callback(function (array $env) {
                    return !isset($env['RESTIC_PASSWORD']);
                })
            )
            ->willReturn(['exitCode' => 0, 'stdout' => '', 'stderr' => '']);

        $service = new MaintenanceService($mock);
        $service->forget($this->repo, ['keep_last' => 3]);

        $this->assertNotNull($capturedCommand);
        $this->assertContains('--insecure-no-password', $capturedCommand);
    }
}
