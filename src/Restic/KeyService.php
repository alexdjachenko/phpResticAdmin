<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

class KeyService
{
    private CommandRunner $runner;

    public function __construct(CommandRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * Проверяет, что пароль соответствует одному из ключей репозитория.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, error: string}
     */
    public function verifyKey(array $repository, string $password): array
    {
        $verifyRepo = array_merge($repository, ['password' => $password]);
        $command = ResticCommandBuilder::buildCommand(['snapshots', '--json'], $verifyRepo);
        $env = ResticCommandBuilder::buildEnv($verifyRepo);
        $result = $this->runner->run($command, $env, null, 10);

        return [
            'ok' => $result['exitCode'] === 0,
            'error' => $result['stderr'],
        ];
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<int, array{id: string, current: bool, userName: string, created: string}>
     */
    public function listKeys(array $repository): array
    {
        $command = ResticCommandBuilder::buildCommand(['key', 'list', '--json'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
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
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function addKey(array $repository, string $newPassword): array
    {
        // Проверяем, нет ли уже ключа с таким паролем
        $verifyResult = $this->verifyKey($repository, $newPassword);
        if ($verifyResult['ok']) {
            return [
                'ok' => false,
                'output' => '',
                'error' => 'A key with this password already exists in the repository.',
            ];
        }

        $command = ResticCommandBuilder::buildCommand(['key', 'add'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $stdin = $newPassword . "\n" . $newPassword . "\n";
        $result = $this->runner->run($command, $env, $stdin);

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
    public function removeKey(array $repository, string $keyId): array
    {
        $command = ResticCommandBuilder::buildCommand(['key', 'remove', $keyId], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Меняет пароль репозитория.
     *
     * restic 0.19+ `key passwd` не принимает ID ключа — меняется пароль
     * текущего ключа.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function changePassword(array $repository, string $newPassword): array
    {
        $command = ResticCommandBuilder::buildCommand(['key', 'passwd'], $repository);
        $env = ResticCommandBuilder::buildEnv($repository);
        $stdin = $newPassword . "\n" . $newPassword . "\n";
        $result = $this->runner->run($command, $env, $stdin);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
}
