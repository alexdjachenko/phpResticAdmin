<?php

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

        $result = $this->runner->run($command, $env);

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

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
    }
