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
 *   - Метки — нового формата (user#op[repoId]rand16), чтобы проверять извлечение.
 *
 * Критерий успеха: все assert проходят.
 *
 * Требует: бинарник tsp в PATH (иначе тесты скипаются).
 */
class TspClientTest extends TestCase
{
    private const LABEL_CHECK = 'alice#checkr10123456789abcdef';
    private const LABEL_SNAPSHOTS = 'bob#snapshotsr20123456789abcdef';
    private const LABEL_COPYSNAP = 'carol#copysnapr30123456789abcdef';

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
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/echo', 'hello']);

        $this->assertGreaterThanOrEqual(0, $result['id'], 'enqueue must return a numeric job id');
        $this->assertSame(self::LABEL_CHECK, $result['label']);
    }

    /** list() содержит только что поставленную задачу с её label. */
    public function testListContainsEnqueuedJob(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/echo', 'hello']);
        $this->tsp->wait($result['id']);

        $found = null;
        foreach ($this->tsp->list() as $job) {
            if ($job['id'] === $result['id']) {
                $found = $job;
                break;
            }
        }

        $this->assertNotNull($found, 'enqueued job should appear in list()');
        $this->assertSame(self::LABEL_CHECK, $found['label']);
    }

    /**
     * Метки с op на hex-букву (check, copysnap) и с префиксом (snapshots) не
     * «обрезаются» при извлечении из `tsp -l`.
     */
    public function testLabelsAreNotTruncated(): void
    {
        foreach ([self::LABEL_CHECK, self::LABEL_SNAPSHOTS, self::LABEL_COPYSNAP] as $label) {
            $result = $this->tsp->enqueue($label, ['/bin/echo', 'x']);
            $this->tsp->wait($result['id']);
        }

        $labels = [];
        foreach ($this->tsp->list() as $job) {
            if ($job['label'] !== null) {
                $labels[] = $job['label'];
            }
        }

        $this->assertContains(self::LABEL_CHECK, $labels);
        $this->assertContains(self::LABEL_SNAPSHOTS, $labels);
        $this->assertContains(self::LABEL_COPYSNAP, $labels);
    }

    /** cat возвращает stdout задачи. */
    public function testCatReturnsOutput(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/echo', 'hello-from-tsp']);
        $this->tsp->wait($result['id']);

        $this->assertStringContainsString('hello-from-tsp', $this->tsp->cat($result['id']));
    }

    /** cat после завершения задачи читает полный вывод (без гонки). */
    public function testCatDelayedReadAfterCompletion(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/echo', 'delayed-output']);
        $this->tsp->wait($result['id']);

        $output = $this->tsp->cat($result['id']);
        $this->assertStringContainsString('delayed-output', $output);
    }

    /** outputFile возвращает существующий файл после завершения задачи. */
    public function testOutputFileExists(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/echo', 'x']);
        $this->tsp->wait($result['id']);

        $file = $this->tsp->outputFile($result['id']);
        $this->assertNotNull($file, 'outputFile should return a path for a finished job');
        $this->assertFileExists($file);
    }

    /** wait возвращает код возврата задачи (0 для true, 1 для false). */
    public function testWaitReturnsExitCode(): void
    {
        $ok = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/true']);
        $fail = $this->tsp->enqueue(self::LABEL_SNAPSHOTS, ['/bin/false']);

        $this->assertSame(0, $this->tsp->wait($ok['id']));
        $this->assertNotSame(0, $this->tsp->wait($fail['id']));
    }

    /** state меняется с queued/running на finished. */
    public function testStateChangesFromRunningToFinished(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_CHECK, ['/bin/sleep', '1']);

        $initial = $this->tsp->state($result['id']);
        $this->assertContains($initial, ['queued', 'running'], 'initial state must be queued or running');

        $this->tsp->wait($result['id']);
        $this->assertSame('finished', $this->tsp->state($result['id']));
    }

    /** info содержит label задачи. */
    public function testLabelAppearsInInfo(): void
    {
        $result = $this->tsp->enqueue(self::LABEL_SNAPSHOTS, ['/bin/echo', 'x']);
        $this->tsp->wait($result['id']);

        $info = $this->tsp->info($result['id']);
        $this->assertSame(self::LABEL_SNAPSHOTS, $info['label']);
    }

    /**
     * Переменная окружения видна внутри одиночной фоновой задачи.
     */
    public function testEnvIsPassedToJob(): void
    {
        $result = $this->tsp->enqueue(
            self::LABEL_CHECK,
            ['/bin/sh', '-c', 'echo $PHPRESTICADMIN_TEST_ENV'],
            ['PHPRESTICADMIN_TEST_ENV' => 'phpResticAdminTestValue']
        );

        $this->tsp->wait($result['id']);

        $this->assertStringContainsString('phpResticAdminTestValue', $this->tsp->cat($result['id']));
    }

    /**
     * Две задачи с разным окружением, вторая стоит в очереди (slots = 1),
     * и всё равно видит своё окружение.
     *
     * Это решающий тест на доставку env в задачу, стоящую в очереди: если
     * tsp-сервер захватывает окружение при старте, вторая задача увидит
     * окружение первой, и тест упадёт.
     */
    public function testTwoTasksWithDifferentEnvs(): void
    {
        $taskA = $this->tsp->enqueue(
            self::LABEL_CHECK,
            ['/bin/sh', '-c', 'echo $PHPRESTICADMIN_ENV_TEST'],
            ['PHPRESTICADMIN_ENV_TEST' => 'valueA']
        );

        $taskB = $this->tsp->enqueue(
            self::LABEL_SNAPSHOTS,
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
