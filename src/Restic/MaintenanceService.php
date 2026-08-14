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
        $command = ResticCommandBuilder::buildCommand(['check'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 0);

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
        $command = ResticCommandBuilder::buildCommand(['prune'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 0);

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
        // rebuild-index устарел в restic; актуальная команда — repair index.
        $command = ResticCommandBuilder::buildCommand(['repair', 'index'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 0);

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
        $command = ResticCommandBuilder::buildCommand(['unlock'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 0);

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

        $command = ResticCommandBuilder::buildCommand($args, $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 0);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Возвращает общую статистику репозитория (restic stats --json).
     *
     * Тяжёлая операция для больших репозиториев — запускается только
     * по запросу пользователя со страницы обслуживания.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function stats(array $repository): array
    {
        $command = ResticCommandBuilder::buildCommand(['stats', '--json'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 300);

        $output = $result['stdout'];
        if ($result['exitCode'] === 0 && $output !== '') {
            $decoded = json_decode($output, true);
            if (is_array($decoded)) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($pretty !== false) {
                    $output = $pretty;
                }
            }
        }

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $output,
            'error' => $result['stderr'],
        ];
    }
}
