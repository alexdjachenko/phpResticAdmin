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
        $command = ['restic', 'snapshots', '--json', '--repo', $repository['path']];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

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
        $command = ['restic', 'init', '--repo', $repository['path']];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

        $result = $this->runner->run($command, $env);

        // Если init упал, а stderr пуст — берём ошибку из stdout.
        // Некоторые ошибки restic (например, предупреждения) попадают в stdout
        // при использовании --json-флагов. CommandRunner также добавляет
        // fallback-сообщение при exitCode != 0 и пустых stdout/stderr.
        $error = $result['stderr'] !== '' ? $result['stderr'] : $result['stdout'];

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
        $command = ['restic', 'backup', '--repo', $repository['path'], ...$backupPaths];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

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
        $command = ['restic', 'backup', '--repo', $repository['path'], ...$backupPaths];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

        $result = $this->runner->run($command, $env, null, 0);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
}
