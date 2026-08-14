<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

class RepositoryService
{
    private CommandRunner $runner;

    public function __construct(CommandRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function testConnection(array $repository): array
    {
        // restic cat config — быстрая проверка, что репозиторий существует
        // и доступен, без перебора всех снепшотов (snapshots может виснуть
        // на больших удалённых репозиториях).
        $command = ResticCommandBuilder::buildCommand(['cat', 'config'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);

        $result = $this->runner->run($command, $env, null, 10);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Инициализирует restic-репозиторий.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function init(array $repository): array
    {
        $command = ResticCommandBuilder::buildCommand(['init'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);

        $result = $this->runner->run($command, $env);

        $error = $result['stderr'] !== '' ? $result['stderr'] : $result['stdout'];

        // Fallback: если и stderr, и stdout пусты — даём осмысленное сообщение
        if ($error === '' && $result['exitCode'] !== 0) {
            $error = 'restic exited with code ' . $result['exitCode'] . ' (no output)';
        }

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $error,
        ];
    }

    /**
     * Запускает restic backup со стримингом вывода в браузер.
     *
     * @param array<string, mixed> $repository
     * @param array<int, string> $backupPaths
     */
    public function backup(array $repository, array $backupPaths): void
    {
        $command = ResticCommandBuilder::buildCommand(['backup', ...$backupPaths], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);

        $this->runner->runStream($command, $env);
    }

    /**
     * Запускает restic backup и возвращает результат (без стриминга).
     *
     * @param array<string, mixed> $repository
     * @param array<int, string> $backupPaths
     * @return array{ok: bool, output: string, error: string}
     */
    public function backupSync(array $repository, array $backupPaths): array
    {
        $command = ResticCommandBuilder::buildCommand(['backup', ...$backupPaths], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);

        $result = $this->runner->run($command, $env, null, 0);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
}
