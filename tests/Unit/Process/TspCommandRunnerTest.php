<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Process\TspClient;
use App\Process\TspCommandRunner;
use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест TspCommandRunner (синхронный адаптер поверх tsp).
 *
 * Цель: проверить, что run() ставит команду в очередь, ожидает её завершения
 *       и возвращает exitCode/stdout/stderr, а также обрабатывает таймаут.
 *
 * Сценарий:
 *   - run(['/bin/echo','hello']) → exitCode 0, stdout содержит hello.
 *   - run(['/bin/false']) → exitCode != 0.
 *   - run(['/bin/sleep','5'], timeout 1) → stderr содержит 'timed out'.
 *   - run со stdin → делегируется прямому CommandRunner.
 *
 * Критерий успеха: все assert проходят.
 *
 * Требует: бинарник tsp в PATH (иначе тесты скипаются).
 */
class TspCommandRunnerTest extends TestCase
{
    /** @var string */
    private string $baseDir;
    /** @var TspCommandRunner */
    private TspCommandRunner $runner;

    protected function setUp(): void
    {
        $direct = new CommandRunner();
        $check = $direct->run(['tsp', '-V']);
        if ($check['exitCode'] !== 0) {
            $this->markTestSkipped('tsp (task-spooler) is not available');
        }

        $this->baseDir = sys_get_temp_dir() . '/phpresticadmin_tsp_runner_' . uniqid();
        $tspDir = $this->baseDir . '/tsp';
        mkdir($tspDir, 0777, true);

        $tsp = new TspClient($direct, $this->baseDir, $tspDir . '/socket');
        $this->runner = new TspCommandRunner($tsp, $direct);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    /** run(['/bin/echo','hello']) → exitCode 0 и stdout содержит hello. */
    public function testRunCapturesStdout(): void
    {
        $result = $this->runner->run(['/bin/echo', 'hello-from-runner'], [], null, 30);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('hello-from-runner', $result['stdout']);
    }

    /** run(['/bin/false']) → exitCode != 0. */
    public function testRunReturnsNonZeroExitCodeForFailure(): void
    {
        $result = $this->runner->run(['/bin/false'], [], null, 30);

        $this->assertNotSame(0, $result['exitCode']);
    }

    /** run со stdin делегируется прямому CommandRunner. */
    public function testRunWithStdinDelegatesToDirectRunner(): void
    {
        $result = $this->runner->run(['/bin/cat'], [], "test input\n", 30);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('test input', $result['stdout']);
    }

    /** Таймаут: sleep 5 с timeout 1 → stderr содержит 'timed out'. */
    public function testRunTimesOut(): void
    {
        $result = $this->runner->run(['/bin/sleep', '5'], [], null, 1);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('timed out', $result['stderr']);
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
