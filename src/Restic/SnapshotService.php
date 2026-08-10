<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

class SnapshotService
{
    private CommandRunner $runner;

    public function __construct(CommandRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    public function listSnapshots(array $repository): array
    {
        $command = $this->buildCommand(['snapshots', '--json'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $decoded = json_decode($result['stdout'], true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Возвращает полную статистику одного снепшота (restic stats --json).
     * Тяжёлая операция, вызывается только по запросу пользователя.
     *
     * @param array<string, mixed> $repository
     * @return array<string, mixed>|null
     */
    public function getStats(array $repository, string $snapId): ?array
    {
        $command = $this->buildCommand(['stats', '--json', '--mode', 'raw-data'], $repository);
        $command[] = $snapId;

        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] !== 0) {
            return null;
        }

        $decoded = json_decode($result['stdout'], true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded[0] ?? $decoded;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<string, mixed>|null
     */
    public function getSnapshot(array $repository, string $snapId): ?array
    {
        $snapshots = $this->listSnapshots($repository);

        foreach ($snapshots as $snap) {
            if (($snap['short_id'] ?? '') === $snapId || ($snap['id'] ?? '') === $snapId) {
                return $snap;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function addTag(array $repository, string $snapId, string $tag): array
    {
        return $this->tagOperation($repository, $snapId, $tag, '--add');
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function removeTag(array $repository, string $snapId, string $tag): array
    {
        return $this->tagOperation($repository, $snapId, $tag, '--remove');
    }

    /**
     * Копирует снепшот из source-репозитория в destination-репозиторий.
     *
     * @param array{id: string, path: string, password: ?string, env?: array<string, string>} $sourceRepo
     * @param array{id: string, path: string, password: ?string, env?: array<string, string>} $destRepo
     * @return array{ok: bool, output: string, error: string}
     */
    public function copy(array $sourceRepo, array $destRepo, string $snapshotId): array
    {
        if ($snapshotId === '') {
            return ['ok' => false, 'output' => '', 'error' => 'No snapshot ID provided'];
        }

        $cmd = ['restic'];

        if (empty($destRepo['password'])) {
            $cmd[] = '--insecure-no-password';
        }

        $cmd[] = '--repo';
        $cmd[] = $destRepo['path'];
        $cmd[] = 'copy';
        $cmd[] = '--from-repo';
        $cmd[] = $sourceRepo['path'];
        $cmd[] = $snapshotId;

        $env = $sourceRepo['env'] ?? [];
        $destEnv = $destRepo['env'] ?? [];

        foreach ($destEnv as $key => $value) {
            $env[$key] = $value;
        }

        if (!empty($destRepo['password'])) {
            $env['RESTIC_PASSWORD'] = $destRepo['password'];
        }

        if (!empty($sourceRepo['password'])) {
            $env['RESTIC_FROM_PASSWORD'] = $sourceRepo['password'];
        } else {
            $env['RESTIC_FROM_PASSWORD'] = '';
        }

        $result = $this->runner->run($cmd, $env);

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
    private function tagOperation(array $repository, string $snapId, string $tag, string $operation): array
    {
        $command = $this->buildCommand(
            ['tag', $operation, $tag, $snapId],
            $repository
        );
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Строит команду restic. --insecure-no-password — глобальный флаг,
     * должен идти ДО подкоманды, иначе restic примет его за snapshot ID.
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
