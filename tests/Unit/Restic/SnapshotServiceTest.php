<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use App\Restic\SnapshotService;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест SnapshotService (listSnapshots, listLatestSnapshots через моки).
 *
 * Цель: проверить формирование команд restic для списка снепшотов,
 *       в том числе флаг --latest N для последних снепшотов.
 *
 * Сценарий:
 *   - listLatestSnapshots добавляет --json и --latest N перед позиционными
 *     аргументами, парсит JSON-ответ.
 *   - listLatestSnapshots возвращает [] при ошибке restic.
 *
 * Критерий успеха: моки проверяют аргументы команды и возврат данных.
 */
class SnapshotServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $repo;

    protected function setUp(): void
    {
        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test Repo',
            'type' => 'local',
            'path' => '/tmp/test-repo',
            'password' => null,
        ];
    }

    /** listLatestSnapshots добавляет --latest N и парсит JSON. */
    public function testListLatestSnapshotsAddsLatestFlag(): void
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
                120
            )
            ->willReturn([
                'exitCode' => 0,
                'stdout' => '[{"id":"abc123","short_id":"abc123"}]',
                'stderr' => '',
            ]);

        $service = new SnapshotService($mock);
        $snapshots = $service->listLatestSnapshots($this->repo, 5);

        $this->assertCount(1, $snapshots);
        $this->assertSame('abc123', $snapshots[0]['short_id']);

        $this->assertNotNull($capturedCommand);
        $latestPos = array_search('--latest', $capturedCommand, true);
        $this->assertIsInt($latestPos, '--latest flag should be present');
        $this->assertSame('5', $capturedCommand[$latestPos + 1] ?? null, '--latest should be followed by the limit');
        $this->assertContains('--json', $capturedCommand);
    }

    /** listLatestSnapshots возвращает пустой массив при ошибке restic. */
    public function testListLatestSnapshotsReturnsEmptyOnError(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Is there a repository at this location?',
            ]);

        $service = new SnapshotService($mock);
        $snapshots = $service->listLatestSnapshots($this->repo, 5);

        $this->assertSame([], $snapshots);
        }

        /** getSnapshotById строит snapshots --json <id> без --latest и парсит первый элемент. */
        public function testGetSnapshotByIdQueriesSingleSnapshot(): void
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
            ->willReturn([
                'exitCode' => 0,
                'stdout' => '[{"id":"abc123","short_id":"abc123"}]',
                'stderr' => '',
            ]);

        $service = new SnapshotService($mock);
        $snap = $service->getSnapshotById($this->repo, 'abc123');

        $this->assertNotNull($snap);
        $this->assertSame('abc123', $snap['short_id']);
        $this->assertNotNull($capturedCommand);
        $this->assertContains('snapshots', $capturedCommand);
        $this->assertContains('--json', $capturedCommand);
        $this->assertContains('abc123', $capturedCommand);
        $this->assertNotContains('--latest', $capturedCommand);
        }

        /** getSnapshot запрашивает один снепшот по ID, а не полный listSnapshots. */
        public function testGetSnapshotQueriesById(): void
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
            ->willReturn([
                'exitCode' => 0,
                'stdout' => '[{"id":"abc123","short_id":"abc123"}]',
                'stderr' => '',
            ]);

        $service = new SnapshotService($mock);
        $snap = $service->getSnapshot($this->repo, 'abc123');

        $this->assertNotNull($snap);
        $this->assertNotNull($capturedCommand);
        $this->assertContains('abc123', $capturedCommand, 'getSnapshot must query by ID, not list all snapshots');
        $this->assertNotContains('--latest', $capturedCommand);
        }

        /** getSnapshotById при ошибке restic → null. */
        public function testGetSnapshotByIdReturnsNullOnError(): void
        {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 1, 'stdout' => '', 'stderr' => 'not found']);

        $service = new SnapshotService($mock);

        $this->assertNull($service->getSnapshotById($this->repo, 'abc123'));
        }
        }
