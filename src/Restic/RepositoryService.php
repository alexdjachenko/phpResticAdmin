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
     * @param array{id: string, name: string, type: string, path: string, password: ?string, env?: array<string, string>} $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function testConnection(array $repository): array
    {
        $command = $this->buildCommand(['snapshots', '--json'], $repository);
        $env = $this->buildEnv($repository);

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
     * @param array{path: string, password: ?string, env?: array<string, string>} $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function init(array $repository): array
    {
        $command = $this->buildCommand(['init'], $repository);
        $env = $this->buildEnv($repository);

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
        $command = $this->buildCommand(['backup', ...$backupPaths], $repository);
        $env = $this->buildEnv($repository);

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
        $command = $this->buildCommand(['backup', ...$backupPaths], $repository);
        $env = $this->buildEnv($repository);

        $result = $this->runner->run($command, $env, null, 0);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Строит команду restic. Глобальные флаги (--repo, --insecure-no-password)
     * должны идти ДО подкоманды, иначе restic 0.19+ может их не распознать.
     *
     * @param array<int, string> $subcommandArgs
     * @param array<string, mixed> $repository
     * @return array<int, string>
     */
    private function buildCommand(array $subcommandArgs, array $repository): array
    {
        $cmd = ['restic'];

        if (empty($repository['password'])) {
            $cmd[] = '--insecure-no-password';
        }

        $cmd[] = '--repo';
        $cmd[] = $repository['path'];

        return array_merge($cmd, $subcommandArgs);
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<string, string>
     */
    private function buildEnv(array $repository): array
    {
        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        }

        return $env;
    }
}
