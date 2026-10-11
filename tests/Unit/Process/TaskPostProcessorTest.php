<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Cache\CacheManager;
use App\Process\TaskPostProcessor;
use App\Process\TspTaskManager;
use App\Storage\ContentCacheStorage;
use App\Storage\ContentTargets;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест постобработки вывода задачи (реестр op → processor).
 *
 * Цель: проверить, что готовый вывод задачи разбирается и кладётся в кеш для
 *       операций с постобработкой, а для операций без обработчика кеш не
 *       трогается; битый вывод фиксируется как ошибка.
 *
 * Сценарий: TspTaskManager замокан (catResult); ContentCacheStorage — реальный
 *       (сессионная область).
 *
 * Критерий успеха: все assert проходят.
 */
class TaskPostProcessorTest extends TestCase
{
    private ContentCacheStorage $storage;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->storage = new ContentCacheStorage(new CacheManager('session'), 600, 31536000);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    private function processor(TspTaskManager $tasks): TaskPostProcessor
    {
        return new TaskPostProcessor($tasks, $this->storage);
    }

    private function tasksReturning(?array $result): TspTaskManager
    {
        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('catResult')->willReturn($result);
        return $tasks;
    }

    /** snapshots: JSON вывода попадает в кеш как массив. */
    public function testSnapshotsWritesDecodedArray(): void
    {
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);
        $tasks = $this->tasksReturning(['exitCode' => 0, 'output' => '[{"id":"a"}]', 'stderr' => '']);

        $handled = $this->processor($tasks)->process('snapshots', 'alice', 'alice#snapshotspub10123456789abcdef', true, $target);

        $this->assertTrue($handled);
        $this->assertSame([['id' => 'a']], $this->storage->read($target)->value);
    }

    /** snapstats: parseStatsOutput попадает в кеш. */
    public function testSnapstatsWritesParsedStats(): void
    {
        $target = ContentTargets::forStats('r1', 'snap123');
        $json = '{"total_size":42,"total_file_count":3}';
        $tasks = $this->tasksReturning(['exitCode' => 0, 'output' => $json, 'stderr' => '']);

        $handled = $this->processor($tasks)->process('snapstats', 'alice', 'alice#snapstatsr1@snap1230123456789abcdef', true, $target);

        $this->assertTrue($handled);
        $this->assertSame(['total_size' => 42, 'total_file_count' => 3], $this->storage->read($target)->value);
    }

    /** Битый JSON → контент не записан, зафиксирована ошибка. */
    public function testSnapshotsBrokenJsonSetsError(): void
    {
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);
        $tasks = $this->tasksReturning(['exitCode' => 0, 'output' => 'not-json', 'stderr' => '']);

        $handled = $this->processor($tasks)->process('snapshots', 'alice', 'alice#snapshotspub10123456789abcdef', true, $target);

        $this->assertFalse($handled);
        $entry = $this->storage->read($target);
        $this->assertNull($entry->value);
        $this->assertNotEmpty($entry->error);
    }

    /** Ненулевой exit code → контент не записан, ошибка из stderr. */
    public function testNonZeroExitCodeSetsErrorFromStderr(): void
    {
        $target = ContentTargets::forStats('r1', 'snap123');
        $tasks = $this->tasksReturning(['exitCode' => 1, 'output' => '', 'stderr' => 'Fatal: wrong password']);

        $handled = $this->processor($tasks)->process('snapstats', 'alice', 'alice#snapstatsr1@snap1230123456789abcdef', true, $target);

        $this->assertFalse($handled);
        $entry = $this->storage->read($target);
        $this->assertNull($entry->value);
        $this->assertStringContainsString('Fatal', $entry->error);
    }

    /** Операция без обработчика → null, кеш не тронут. */
    public function testOpWithoutProcessorLeavesCacheUntouched(): void
    {
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);
        $tasks = $this->tasksReturning(['exitCode' => 0, 'output' => 'x', 'stderr' => '']);

        $handled = $this->processor($tasks)->process('backup', 'alice', 'alice#backuppub10123456789abcdef', true, $target);

        $this->assertNull($handled);
        $this->assertNull($this->storage->read($target));
    }
}
