<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

class MaintenanceService
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
    public function check(array $repository): array
    {
        $command = $this->buildCommand(['check'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function prune(array $repository): array
    {
        $command = $this->buildCommand(['prune'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function rebuildIndex(array $repository): array
    {
        $command = $this->buildCommand(['rebuild-index'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function unlock(array $repository): array
    {
        $command = $this->buildCommand(['unlock'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * @param array<string, mixed> $repository
     * @param array{keep_daily?: int, keep_weekly?: int, keep_monthly?: int, keep_yearly?: int, keep_last?: int, prune?: bool, dry_run?: bool} $policy
     * @return array{ok: bool, output: string, error: string}
     */
    public function forget(array $repository, array $policy): array
    {
        $args = ['forget'];

        if (!empty($policy['keep_daily'])) {
            $args[] = '--keep-daily';
            $args[] = (string) $policy['keep_daily'];
        }
        if (!empty($policy['keep_weekly'])) {
            $args[] = '--keep-weekly';
            $args[] = (string) $policy['keep_weekly'];
        }
        if (!empty($policy['keep_monthly'])) {
            $args[] = '--keep-monthly';
            $args[] = (string) $policy['keep_monthly'];
        }
        if (!empty($policy['keep_yearly'])) {
            $args[] = '--keep-yearly';
            $args[] = (string) $policy['keep_yearly'];
        }
        if (!empty($policy['keep_last'])) {
            $args[] = '--keep-last';
            $args[] = (string) $policy['keep_last'];
        }
        if (!empty($policy['prune'])) {
            $args[] = '--prune';
        }
        if (!empty($policy['dry_run'])) {
            $args[] = '--dry-run';
        }

        $command = $this->buildCommand($args, $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
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
