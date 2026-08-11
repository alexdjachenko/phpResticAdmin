<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест CommandRunner (обёртка proc_open).
 *
 * Цель: проверить выполнение команд через proc_open: захват stdout/stderr,
 *       передачу stdin, переменные окружения, таймаут, обработку ошибок.
 *
 * Сценарий:
 *   - Несуществующая команда → ненулевой exitCode, stderr не пуст.
 *   - Неисполняемый файл → ошибка.
 *   - /bin/echo → stdout содержит ожидаемую строку, stderr пуст.
 *   - ls несуществующего пути → stderr не пуст.
 *   - stdin передаётся в процесс (/bin/cat).
 *   - Таймаут: sleep 5 с таймаутом 1с.
 *   - Переменные окружения пробрасываются (/bin/sh).
 *
 * Критерий успеха: все assertSame/assertStringContainsString проходят.
 */
class CommandRunnerTest extends TestCase
{
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new CommandRunner();
    }

    /** Несуществующий бинарник → ненулевой exitCode, stderr содержит имя команды. */
    public function testRunReturnsMeaningfulErrorForNonexistentCommand(): void
    {
        $result = $this->runner->run(['nonexistent_binary_xyz_12345']);

        $this->assertNotSame(0, $result['exitCode'], 'exitCode should not be 0 for nonexistent binary');
        $this->assertNotEmpty($result['stderr'], 'stderr should not be empty when command is not found');
        $this->assertStringContainsString('nonexistent_binary_xyz_12345', $result['stderr']);
    }

    /** Неисполняемый файл → ошибка. */
    public function testRunReturnsMeaningfulErrorForNonExecutableFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/phpresticadmin_test_not_exec_' . uniqid();
        file_put_contents($tmpFile, '<?php echo "hello";');
        chmod($tmpFile, 0644);

        $result = $this->runner->run([$tmpFile]);

        unlink($tmpFile);

        $this->assertNotSame(0, $result['exitCode'], 'exitCode should not be 0 for non-executable file');
        $this->assertNotEmpty($result['stderr'], 'stderr should not be empty for non-executable file');
    }

    /** Успешная команда (/bin/echo) → stdout содержит вывод, stderr пуст. */
    public function testRunCapturesStdoutOnSuccess(): void
    {
        $result = $this->runner->run(['/bin/echo', 'hello world']);

        $this->assertSame(0, $result['exitCode'], sprintf(
            'Expected exitCode 0, got %d. stderr: "%s", stdout: "%s"',
            $result['exitCode'],
            $result['stderr'],
            $result['stdout']
        ));
        $this->assertStringContainsString('hello world', $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }

    /** Команда с ошибкой (ls несуществующего пути) → stderr не пуст. */
    public function testRunCapturesStderrOnFailure(): void
    {
        $result = $this->runner->run(['/bin/ls', '/nonexistent_path_' . uniqid()]);

        $this->assertNotSame(0, $result['exitCode'], sprintf(
            'Expected nonzero exitCode, got %d. stderr: "%s"',
            $result['exitCode'],
            $result['stderr']
        ));
        $this->assertNotEmpty($result['stderr'], 'stderr should not be empty when command fails');
    }

    /** stdin передаётся в процесс (/bin/cat). */
    public function testRunWithStdin(): void
    {
        $result = $this->runner->run(['/bin/cat'], [], "test input\n");

        $this->assertSame(0, $result['exitCode'], sprintf(
            'Expected exitCode 0, got %d. stderr: "%s"',
            $result['exitCode'],
            $result['stderr']
        ));
        $this->assertStringContainsString('test input', $result['stdout']);
    }

    /** Таймаут: sleep 5 с таймаутом 1с → exitCode != 0, stderr содержит 'timed out'. */
    public function testRunHandlesTimeout(): void
    {
        $result = $this->runner->run(['/bin/sleep', '5'], [], null, 1);

        $this->assertNotSame(0, $result['exitCode'], sprintf(
            'Expected nonzero exitCode for timed out process, got %d. stderr: "%s"',
            $result['exitCode'],
            $result['stderr']
        ));
        $this->assertStringContainsString('timed out', $result['stderr']);
    }

    /** Переменные окружения пробрасываются в процесс. */
    public function testRunWithEnvPassesVariables(): void
    {
        $result = $this->runner->run(
            ['/bin/sh', '-c', 'echo $TEST_VAR'],
            ['TEST_VAR' => 'phpResticAdminTestValue']
        );

        $this->assertSame(0, $result['exitCode'], sprintf(
            'Expected exitCode 0, got %d. stderr: "%s"',
            $result['exitCode'],
            $result['stderr']
        ));
        $this->assertStringContainsString('phpResticAdminTestValue', $result['stdout']);
    }
}
