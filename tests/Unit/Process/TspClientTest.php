<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Process\TspClient;
use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест TspClient (реальные вызовы tsp с задачами-моками).
 *
 * Цель: проверить низкоуровневую обёртку над task-spooler: enqueue, list,
 *       state, outputFile, cat, wait, info и передачу окружения в задачу.
 *
 * Сценарий:
 *   - Каждый тест использует уникальный TS_SOCKET (изолированная очередь).
 *   - Задачи-моки: /bin/echo, /bin/sleep, /bin/true, /bin/false, /bin/sh -c.
 *
 * Критерий успеха: все assert проходят.
 *
 * Требует: бинарник tsp в PATH (иначе тесты скипаются).
 */
class TspClientTest extends TestCase
{
    /** @var string */
    private string $baseDir;
    /** @var TspClient */
    private TspClient $tsp;

    protected function setUp(): void
    {
        $runner = new CommandRunner();
        $check = $runner->run(['tsp', '-V']);
        if ($check['exitCode'] !== 0) {
            $this->markTestSkipped('tsp (task-spooler) is not available');
        }

        $this->baseDir = sys_get_temp_dir() . '/phpresticadmin_tsp_test_' . uniqid();
        $tspDir = $this->baseDir . '/tsp';
        mkdir($tspDir, 0777, true);

        $this->tsp = new TspClient($runner, $this->baseDir, $tspDir . '/socket');
    }

    protected function tearDown(): void
    {
        if (isset($this->tsp)) {
            $this->tsp->clearFinished();
        }
        $this->removeDir($this->baseDir);
    }

    /** enqueue возвращает числовой id и переданный label. */
    public function testEnqueueReturnsIdAndLabel(): void
    {
        $result = $this->tsp->enqueue('alice#abc123', ['/bin/echo', 'hello']);

        $this->assertGreaterThanOrEqual(0, $result['id'], 'enqueue must return a numeric job id');
        $this->assertSame('alice#abc123', $result['label']);
    }

    /** list() содержит только что поставленную задачу с её label. */
    public function testListContainsEnqueuedJob(): void
    {
        $result = $this->tsp->enqueue('alice#3f2a9c1b', ['/bin/echo', 'hello']);
        $this->tsp->wait($result['id']);

        $jobs = $this->tsp->list();

        $found = null;
        foreach ($jobs as $job) {
            if ($job['id'] === $result['id']) {
                $found = $job;
                break;
            }
        }

        $this->assertNotNull($found, 'enqueued job should appear in list()');
        $this->assertSame('alice#3f2a9c1b', $found['label']);
    }

    /** cat возвращает stdout задачи. */
    public function testCatReturnsOutput(): void
    {
        $result = $this->tsp->enqueue('alice#cat1', ['/bin/echo', 'hello-from-tsp']);
        $this->tsp->wait($result['id']);

        $this->assertStringContainsString('hello-from-tsp', $this->tsp->cat($result['id']));
    }

    /** cat после завершения задачи читает полный вывод (без гонки). */
    public function testCatDelayedReadAfterCompletion(): void
    {
        $result = $this->tsp->enqueue('alice#cat2', ['/bin/echo', 'delayed-output']);
        $this->tsp->wait($result['id']);

        $output = $this->tsp->cat($result['id']);
        $this->assertStringContainsString('delayed-output', $output);
    }

    /** outputFile возвращает существующий файл после завершения задачи. */
    public function testOutputFileExists(): void
    {
        $result = $this->tsp->enqueue('alice#of1', ['/bin/echo', 'x']);
        $this->tsp->wait($result['id']);

        $file = $this->tsp->outputFile($result['id']);
        $this->assertNotNull($file, 'outputFile should return a path for a finished job');
        $this->assertFileExists($file);
    }

    /** wait возвращает код возврата задачи (0 для true, 1 для false). */
    public function testWaitReturnsExitCode(): void
    {
        $ok = $this->tsp->enqueue('alice#w1', ['/bin/true']);
        $fail = $this->tsp->enqueue('alice#w2', ['/bin/false']);

        $this->assertSame(0, $this->tsp->wait($ok['id']));
        $this->assertNotSame(0, $this->tsp->wait($fail['id']));
    }

    /** state меняется с queued/running на finished. */
    public function testStateChangesFromRunningToFinished(): void
    {
        $result = $this->tsp->enqueue('alice#st1', ['/bin/sleep', '1']);

        $initial = $this->tsp->state($result['id']);
        $this->assertContains($initial, ['queued', 'running'], 'initial state must be queued or running');

        $this->tsp->wait($result['id']);
        $this->assertSame('finished', $this->tsp->state($result['id']));
    }

    /** info содержит label задачи. */
    public function testLabelAppearsInInfo(): void
    {
        $result = $this->tsp->enqueue('alice#3f2a9c1b', ['/bin/echo', 'x']);
        $this->tsp->wait($result['id']);

        $info = $this->tsp->info($result['id']);
        $this->assertSame('alice#3f2a9c1b', $info['label']);
    }

    /**
     * Переменная окружения видна внутри одиночной фоновой задачи.
     *
     * Важно: очередь создаётся заново для этого теста (уникальный TS_SOCKET),
     * поэтому tsp-сервер стартует с окружением вызова enqueue.
     */
    public function testEnvIsPassedToJob(): void
    {
        $result = $this->tsp->enqueue(
            'alice#env1',
            ['/bin/sh', '-c', 'echo $PHPRESTICADMIN_TEST_ENV'],
            ['PHPRESTICADMIN_TEST_ENV' => 'phpResticAdminTestValue']
        );

        $this->tsp->wait($result['id']);

        $this->assertStringContainsString('phpResticAdminTestValue', $this->tsp->cat($result['id']));
    }

    /**
     * Две задачи с РАЗНЫМ окружением, каждая выводит своё значение.
     *
     * Это решающий тест: если tsp-сервер захватывает окружение при старте,
     * вторая задача увидит окружение первой, и тест упадёт. Если env
     * доставляется в задачу независимо — обе задачи напечатают своё значение.
     */
    public function testTwoTasksWithDifferentEnvs(): void
    {
        $taskA = $this->tsp->enqueue(
            'alice#envA',
            ['/bin/sh', '-c', 'echo $PHPRESTICADMIN_ENV_TEST'],
            ['PHPRESTICADMIN_ENV_TEST' => 'valueA']
        );

        $taskB = $this->tsp->enqueue(
            'alice#envB',
            ['/bin/sh', '-c', 'echo $PHPRESTICADMIN_ENV_TEST'],
            ['PHPRESTICADMIN_ENV_TEST' => 'valueB']
        );

        $this->tsp->wait($taskA['id']);
        $this->tsp->wait($taskB['id']);

        $this->assertStringContainsString('valueA', $this->tsp->cat($taskA['id']));
        $this->assertStringContainsString('valueB', $this->tsp->cat($taskB['id']));
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
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
