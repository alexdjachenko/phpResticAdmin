<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

class CommandRunnerTest extends TestCase
{
    private CommandRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new CommandRunner();
    }

    public function testRunReturnsMeaningfulErrorForNonexistentCommand(): void
    {
        $result = $this->runner->run(['nonexistent_binary_xyz_12345']);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertNotEmpty($result['stderr'], 'stderr should not be empty when command is not found');
        $this->assertStringContainsString('nonexistent_binary_xyz_12345', $result['stderr']);
    }

    public function testRunReturnsMeaningfulErrorForNonExecutableFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/phpresticadmin_test_not_exec_' . uniqid();
        file_put_contents($tmpFile, '<?php echo "hello";');
        chmod($tmpFile, 0644);

        $result = $this->runner->run([$tmpFile]);

        unlink($tmpFile);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertNotEmpty($result['stderr'], 'stderr should not be empty for non-executable file');
    }

    public function testRunCapturesStdoutOnSuccess(): void
    {
        $result = $this->runner->run(['echo', 'hello world']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('hello world', $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }

    public function testRunCapturesStderrOnFailure(): void
    {
        // 'ls' with a non-existent file produces stderr
        $result = $this->runner->run(['ls', '/nonexistent_path_' . uniqid()]);

        $this->assertNotSame(0, $result['exitCode']);
        // ls writes errors to stderr
        $this->assertNotEmpty($result['stderr']);
    }

    public function testRunWithStdin(): void
    {
        // cat echoes stdin to stdout
        $result = $this->runner->run(['cat'], [], "test input\n");

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('test input', $result['stdout']);
    }

    public function testRunHandlesTimeout(): void
    {
        // sleep 5 with 1s timeout should time out
        $result = $this->runner->run(['sleep', '5'], [], null, 1);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('timed out', $result['stderr']);
    }

    public function testRunWithEnvPassesVariables(): void
    {
        $result = $this->runner->run(
            ['sh', '-c', 'echo $TEST_VAR'],
            ['TEST_VAR' => 'phpResticAdminTestValue']
        );

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('phpResticAdminTestValue', $result['stdout']);
    }
}
