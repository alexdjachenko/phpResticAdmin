<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

use App\Process\TspTaskManager;

/**
 * Запускает тяжёлые restic-операции в фоне через tsp.
 *
 * Единая точка сборки команд и окружения для фоновых задач. Обязана
 * использовать ResticCommandBuilder::buildCommand()/buildEnv(), а не
 * собирать команды вручную.
 */
class ResticTaskService
{
    private TspTaskManager $tasks;

    public function __construct(TspTaskManager $tasks)
    {
        $this->tasks = $tasks;
    }

    /**
     * @param array<string, mixed> $repo
     * @param array<int, string> $backupPaths
     * @return array{label: string, id: int}
     */
    public function startBackup(array $repo, array $backupPaths): array
    {
        $command = ResticCommandBuilder::buildCommand(['backup', ...$backupPaths], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);
        return $this->tasks->start($this->username(), $command, $env);
    }

    /**
     * @param array<string, mixed> $repo
     * @param array{keep_daily?: int, keep_weekly?: int, keep_monthly?: int, keep_yearly?: int, keep_last?: int, prune?: bool, dry_run?: bool} $policy
     * @return array{label: string, id: int}
     */
    public function startMaintenance(string $operation, array $repo, array $policy = []): array
    {
        $args = $this->maintenanceArgs($operation, $policy);
        $command = ResticCommandBuilder::buildCommand($args, $repo);
        $env = ResticCommandBuilder::buildEnv($repo);
        return $this->tasks->start($this->username(), $command, $env);
    }

    /**
     * @param array<string, mixed> $repo
     * @return array{label: string, id: int}
     */
    public function startInit(array $repo): array
    {
        $command = ResticCommandBuilder::buildCommand(['init'], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);
        return $this->tasks->start($this->username(), $command, $env);
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $dest
     * @return array{label: string, id: int}
     */
    public function startSnapshotCopy(array $source, array $dest, string $snapId): array
    {
        $command = $this->buildCopyCommand($source, $dest, $snapId);
        $env = $this->buildCopyEnv($source, $dest);
        return $this->tasks->start($this->username(), $command, $env);
    }

    /**
     * @param array<string, mixed> $repo
     * @return array{label: string, id: int}
     */
    public function startSnapshotStats(array $repo, string $snapId): array
    {
        $command = ResticCommandBuilder::buildCommand(['stats', '--json', '--mode', 'raw-data'], $repo);
        $command[] = $snapId;
        $env = ResticCommandBuilder::buildEnv($repo);
        // --json: отдельный stderr, чтобы предупреждения restic не ломали JSON
        return $this->tasks->start($this->username(), $command, $env, true);
    }

    /**
     * @param array<string, mixed> $repo
     * @return array{label: string, id: int}
     */
    public function startListSnapshots(array $repo): array
    {
        $command = ResticCommandBuilder::buildCommand(['snapshots', '--json'], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);
        // --json: отдельный stderr, чтобы предупреждения restic не ломали JSON
        return $this->tasks->start($this->username(), $command, $env, true);
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $dest
     * @return array<int, string>
     */
    private function buildCopyCommand(array $source, array $dest, string $snapId): array
    {
        $cmd = ['restic'];

        if (empty($dest['password'])) {
            $cmd[] = '--insecure-no-password';
        }

        $cmd[] = '--repo';
        $cmd[] = \App\Helpers\RepositoryPath::toResticLocation($dest);
        $cmd[] = 'copy';

        if (empty($source['password'])) {
            $cmd[] = '--from-insecure-no-password';
        }

        $cmd[] = '--from-repo';
        $cmd[] = \App\Helpers\RepositoryPath::toResticLocation($source);
        $cmd[] = $snapId;

        return $cmd;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $dest
     * @return array<string, string>
     */
    private function buildCopyEnv(array $source, array $dest): array
    {
        $env = ResticCommandBuilder::buildEnv($source);
        foreach (ResticCommandBuilder::buildEnv($dest) as $key => $value) {
            $env[$key] = $value;
        }

        if (empty($dest['password'])) {
            unset($env['RESTIC_PASSWORD']);
        }

        if (!empty($source['password'])) {
            $env['RESTIC_FROM_PASSWORD'] = (string) $source['password'];
        }

        return $env;
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<int, string>
     */
    private function maintenanceArgs(string $operation, array $policy): array
    {
        switch ($operation) {
            case 'check':
                return ['check'];
            case 'prune':
                return ['prune'];
            case 'repair index':
                return ['repair', 'index'];
            case 'unlock':
                return ['unlock'];
            case 'stats':
                return ['stats', '--json'];
            case 'forget':
                return $this->forgetArgs($policy);
            default:
                throw new \InvalidArgumentException('Unknown maintenance operation: ' . $operation);
        }
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<int, string>
     */
    private function forgetArgs(array $policy): array
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

        return $args;
    }

    private function username(): string
    {
        return \App\Core\App::auth()->user() ?? 'anonymous';
    }
}
