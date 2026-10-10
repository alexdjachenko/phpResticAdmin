<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Restic;

use App\Core\App;
use App\Process\TspClient;
use App\Process\TspTaskManager;
use App\Restic\ResticTaskService;
use App\Process\TaskLabel;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест ResticTaskService (сборка фоновых задач).
 *
 * Цель: для КАЖДОЙ операции, которую интерфейс запускает в фоне
 *       (backup/check/prune/repair index/unlock/forget/stats/init/copy/stats-снепшота/список),
 *       убедиться, что сервис корректно строит код операции для метки и команду
 *       restic. Это защищает от 500-й ошибки в контроллере при неизвестной
 *       операции и от расхождения команды и метки.
 *
 * Сценарий: TspClient замокан (перехватываем enqueue), реальный TspTaskManager;
 *       username фиксируется через сессию. Для каждой операции проверяются
 *       метка (op) и подкоманда restic; для JSON-задач — флаг отдельного stderr.
 *
 * Критерий успеха: все assert проходят.
 */
class ResticTaskServiceTest extends TestCase
{
    /** @var array<int, array{label: string, command: array<int, string>, env: array<string, string>, separateStderr: bool}> */
    private array $captured = [];

    protected function setUp(): void
    {
        App::resetCaches();

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user'] = 'tester';
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user']);
        App::resetCaches();
    }

    /**
     * @return array<string, mixed>
     */
    private function repo(): array
    {
        return ['id' => 'r1', 'type' => 'local', 'local_path' => '/backups/repo', 'password' => null];
    }

    private function service(): ResticTaskService
    {
        $this->captured = [];

        $tsp = $this->createMock(TspClient::class);
        $tsp->method('enqueue')->willReturnCallback(
            function (string $label, array $command, array $env, bool $separateStderr): array {
                $this->captured[] = compact('label', 'command', 'env', 'separateStderr');
                return ['id' => 1, 'label' => $label];
            }
        );

        return new ResticTaskService(new TspTaskManager($tsp));
    }

    /**
     * @param array<int, string> $expectedSubcommand
     */
    private function assertStartedWith(string $expectedOp, array $expectedSubcommand, bool $separateStderr = false): void
    {
        $this->assertCount(1, $this->captured, 'exactly one task must be started');
        $captured = $this->captured[0];

        $parsed = TaskLabel::parse($captured['label']);
        $this->assertNotNull($parsed, 'label must be valid: ' . $captured['label']);
        $this->assertSame('tester', $parsed['username']);
        $this->assertSame($expectedOp, $parsed['op']);
        $this->assertSame('r1', $parsed['repoId']);

        // Подкоманда стоит после глобальных флагов (--repo <path>).
        $position = array_search($expectedSubcommand[0], $captured['command'], true);
        $this->assertNotFalse($position, 'subcommand must be present: ' . $expectedSubcommand[0]);
        $this->assertSame($expectedSubcommand, array_slice($captured['command'], $position, count($expectedSubcommand)));

        $this->assertSame($separateStderr, $captured['separateStderr']);
    }

    /** backup — op backup, подкоманда backup с путями. */
    public function testStartBackup(): void
    {
        $this->service()->startBackup($this->repo(), ['/sources/a']);
        $this->assertStartedWith('backup', ['backup', '/sources/a']);
    }

    /** check — op check. */
    public function testStartCheck(): void
    {
        $this->service()->startMaintenance('check', $this->repo());
        $this->assertStartedWith('check', ['check']);
    }

    /** prune — op prune. */
    public function testStartPrune(): void
    {
        $this->service()->startMaintenance('prune', $this->repo());
        $this->assertStartedWith('prune', ['prune']);
    }

    /** repair index — op repair, подкоманда repair index. */
    public function testStartRebuildIndex(): void
    {
        $this->service()->startMaintenance('repair index', $this->repo());
        $this->assertStartedWith('repair', ['repair', 'index']);
    }

    /** unlock — op unlock. */
    public function testStartUnlock(): void
    {
        $this->service()->startMaintenance('unlock', $this->repo());
        $this->assertStartedWith('unlock', ['unlock']);
    }

    /** stats (репозитория) — op stats. */
    public function testStartStats(): void
    {
        $this->service()->startMaintenance('stats', $this->repo());
        $this->assertStartedWith('stats', ['stats', '--json']);
    }

    /** forget — op forget, политика удержания в команде. */
    public function testStartForget(): void
    {
        $this->service()->startMaintenance('forget', $this->repo(), ['keep_daily' => 7, 'dry_run' => true]);
        $this->assertCount(1, $this->captured);
        $parsed = TaskLabel::parse($this->captured[0]['label']);
        $this->assertNotNull($parsed);
        $this->assertSame('forget', $parsed['op']);
        $this->assertContains('--keep-daily', $this->captured[0]['command']);
        $this->assertContains('7', $this->captured[0]['command']);
        $this->assertContains('--dry-run', $this->captured[0]['command']);
    }

    /** init — op init. */
    public function testStartInit(): void
    {
        $this->service()->startInit($this->repo());
        $this->assertStartedWith('init', ['init']);
    }

    /** copy — op copysnap (глобальная от source, но с repoId). */
    public function testStartSnapshotCopy(): void
    {
        $dest = ['id' => 'r2', 'type' => 'local', 'local_path' => '/backups/dest', 'password' => null];
        $this->service()->startSnapshotCopy($this->repo(), $dest, 'abcd1234');

        $this->assertCount(1, $this->captured);
        $parsed = TaskLabel::parse($this->captured[0]['label']);
        $this->assertNotNull($parsed);
        $this->assertSame('copysnap', $parsed['op']);
        $this->assertContains('copy', $this->captured[0]['command']);
        $this->assertContains('abcd1234', $this->captured[0]['command']);
    }

    /** stats снепшота — op snapstats, отдельный stderr (JSON). */
    public function testStartSnapshotStats(): void
    {
        $this->service()->startSnapshotStats($this->repo(), 'abcd1234');
        $this->assertStartedWith('snapstats', ['stats', '--json', '--mode', 'raw-data'], true);
    }

    /** список снепшотов — op snapshots, отдельный stderr (JSON). */
    public function testStartListSnapshots(): void
    {
        $this->service()->startListSnapshots($this->repo());
        $this->assertStartedWith('snapshots', ['snapshots', '--json'], true);
    }

    /** Неизвестная операция — ошибка программиста, не тихий сбой. */
    public function testUnknownMaintenanceOperationThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->startMaintenance('frobnicate', $this->repo());
    }
}
