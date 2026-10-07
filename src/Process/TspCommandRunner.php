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
 * Синхронный «запусти и верни результат» поверх очереди. Сознательно без
 * потребителя в web-UI: используется REST-эндпоинтами (следующий этап).
 * Не удалять как мёртвый код — это шов под REST (см. AGENTS.md).
 *
 * Задача ставится через TspTaskManager::start() с op = run, поэтому метка
 * несёт владельца (`<username>#run<rand16>`) и задача видна владельцу.
 *
 * Важно: tsp отцепляет stdin от задачи, поэтому вызовы со stdin делегируются
 * прямому CommandRunner (например, restic key add/passwd).
 */
class TspCommandRunner
{
    private TspTaskManager $tasks;
    private TspClient $tsp;
    private CommandRunner $directRunner;

    public function __construct(TspTaskManager $tasks, TspClient $tsp, CommandRunner $directRunner)
    {
        $this->tasks = $tasks;
        $this->tsp = $tsp;
        $this->directRunner = $directRunner;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(array $command, array $env = [], ?string $stdin = null, int $timeout = 30, ?string $username = null): array
    {
        if ($stdin !== null) {
            return $this->directRunner->run($command, $env, $stdin, $timeout);
        }

        // Владелец — только явный username (у REST нет сессии); иначе anonymous.
        $owner = $username ?? 'anonymous';

        $started = $this->tasks->start($owner, 'run', null, $command, $env);
        $id = $started['id'];

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

            if (in_array($state, ['finished', 'skipped', 'unknown'], true)) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $this->tasks->cancel($owner, $started['label'], true);
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
