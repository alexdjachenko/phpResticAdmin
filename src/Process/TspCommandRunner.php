<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

use App\Restic\CommandRunner;

/**
 * Адаптер, реализующий контракт CommandRunner::run() поверх tsp.
 *
 * Используется для постепенной миграции и будущих REST-эндпоинтов, где нужен
 * синхронный «запусти и верни результат». Команда ставится в очередь, затем
 * ожидается её завершение с дедлайном `timeout`, после чего возвращается
 * полный вывод.
 *
 * Важно: tsp отцепляет stdin от задачи, поэтому вызовы со stdin делегируются
 * прямому CommandRunner (например, restic key add/passwd).
 */
class TspCommandRunner
{
    private TspClient $tsp;
    private CommandRunner $directRunner;

    public function __construct(TspClient $tsp, CommandRunner $directRunner)
    {
        $this->tsp = $tsp;
        $this->directRunner = $directRunner;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(array $command, array $env = [], ?string $stdin = null, int $timeout = 30): array
    {
        if ($stdin !== null) {
            return $this->directRunner->run($command, $env, $stdin, $timeout);
        }

        $enqueued = $this->tsp->enqueue('runner#' . bin2hex(random_bytes(8)), $command, $env);
        $id = $enqueued['id'];

        if ($id < 0) {
            return [
                'exitCode' => -1,
                'stdout' => '',
                'stderr' => 'Failed to enqueue task in tsp',
            ];
        }

        $deadline = $timeout > 0 ? microtime(true) + $timeout : PHP_FLOAT_MAX;

        while (true) {
            $state = $this->tsp->state($id);

            if ($state === 'finished' || $state === 'skipped' || $state === 'unknown') {
                break;
            }

            if (microtime(true) >= $deadline) {
                $this->tsp->kill($id);
                return [
                    'exitCode' => -1,
                    'stdout' => '',
                    'stderr' => 'Command timed out after ' . $timeout . ' seconds',
                ];
            }

            usleep(100000);
        }

        $result = $this->tsp->catRaw($id);

        return [
            'exitCode' => $result['exitCode'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
        ];
    }
}
