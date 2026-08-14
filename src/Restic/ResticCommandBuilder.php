<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Restic;

use App\Helpers\RepositoryPath;

/**
 * Единая точка сборки restic-команд и переменных окружения.
 *
 * Глобальные флаги (--repo, --insecure-no-password) идут ДО подкоманды,
 * S3-URL собирается через RepositoryPath.
 */
class ResticCommandBuilder
{
    /**
     * Строит команду restic с глобальными флагами до подкоманды.
     *
     * @param array<int, string> $subcommandArgs
     * @param array<string, mixed> $repository
     * @return array<int, string>
     */
    public static function buildCommand(array $subcommandArgs, array $repository): array
    {
        $cmd = ['restic'];

        if (empty($repository['password'])) {
            $cmd[] = '--insecure-no-password';
        }

        $cmd[] = '--repo';
        $cmd[] = RepositoryPath::toResticLocation($repository);

        return array_merge($cmd, $subcommandArgs);
    }

    /**
     * Собирает переменные окружения для restic.
     *
     * @param array<string, mixed> $repository
     * @return array<string, string>
     */
    public static function buildEnv(array $repository): array
    {
        $env = $repository['env'] ?? [];

        // AWS_ENDPOINT — внутренние метаданные приложения, restic их не использует.
        unset($env['AWS_ENDPOINT']);

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = (string) $repository['password'];
        }

        return $env;
    }
}
