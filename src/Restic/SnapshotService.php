<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

use App\Helpers\RepositoryPath;

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
        $command = ResticCommandBuilder::buildCommand(['snapshots', '--json'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 120);

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
     * Возвращает N последних снепшотов (restic snapshots --json --latest N).
     *
     * Используется на дашборде и странице репозитория, чтобы не тянуть
     * полный список с больших удалённых репозиториев.
     *
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    public function listLatestSnapshots(array $repository, int $limit = 5): array
    {
        $command = ResticCommandBuilder::buildCommand(
            ['snapshots', '--json', '--latest', (string) $limit],
            $repository
        );
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 120);

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
        $command = ResticCommandBuilder::buildCommand(['stats', '--json', '--mode', 'raw-data'], $repository);
        $command[] = $snapId;

        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 300);

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
     * Возвращает один снепшот по ID (restic snapshots --json <id>).
     *
     * В отличие от getSnapshot() не загружает полный список снепшотов,
     * что критично для больших удалённых репозиториев.
     *
     * @param array<string, mixed> $repository
     * @return array<string, mixed>|null
     */
    public function getSnapshotById(array $repository, string $snapId): ?array
    {
        $command = ResticCommandBuilder::buildCommand(['snapshots', '--json', $snapId], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env, null, 120);

        if ($result['exitCode'] !== 0) {
            return null;
        }

        $decoded = json_decode($result['stdout'], true);

        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        return $decoded[0] ?? null;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<string, mixed>|null
     */
    public function getSnapshot(array $repository, string $snapId): ?array
    {
        return $this->getSnapshotById($repository, $snapId);
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
     * @param array<string, mixed> $sourceRepo
     * @param array<string, mixed> $destRepo
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
        $cmd[] = RepositoryPath::toResticLocation($destRepo);
        $cmd[] = 'copy';

        if (empty($sourceRepo['password'])) {
            $cmd[] = '--from-insecure-no-password';
        }

        $cmd[] = '--from-repo';
        $cmd[] = RepositoryPath::toResticLocation($sourceRepo);
        $cmd[] = $snapshotId;

        $env = ResticCommandBuilder::buildEnv($sourceRepo);
        foreach (ResticCommandBuilder::buildEnv($destRepo) as $key => $value) {
            $env[$key] = $value;
        }

        if (empty($destRepo['password'])) {
            unset($env['RESTIC_PASSWORD']);
        }

        if (!empty($sourceRepo['password'])) {
            $env['RESTIC_FROM_PASSWORD'] = (string) $sourceRepo['password'];
        }

        $result = $this->runner->run($cmd, $env, null, 0);

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
        $command = ResticCommandBuilder::buildCommand(
            ['tag', $operation, $tag, $snapId],
            $repository
        );
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
}
