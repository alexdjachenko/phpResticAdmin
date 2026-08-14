<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Helpers;

/**
 * Работа с типоспецифичными полями расположения репозитория.
 *
 * Нормализует значения для хранения, превращает запись репозитория в
 * фактический --repo для restic и проверяет принадлежность локальных путей
 * разрешённым корням.
 */
class RepositoryPath
{
    /**
     * Нормализует значение расположения для хранения в зависимости от типа.
     */
    public static function normalize(string $type, string $value, ?string $repoBaseDir = null): string
    {
        $value = trim($value);

        switch ($type) {
            case 'local':
                if (str_starts_with($value, '/')) {
                    return $value;
                }
                $baseDir = rtrim($repoBaseDir ?? '/backups', '/');
                return $baseDir . '/' . $value;

            case 's3':
                if (str_starts_with($value, 's3:')) {
                    return $value;
                }
                return ltrim($value, '/');

            case 'sftp':
                $value = ltrim($value);
                if (str_starts_with($value, 'sftp:')) {
                    return substr($value, 5);
                }
                return $value;

            case 'rest':
                $value = ltrim($value);
                if (str_starts_with($value, 'rest:')) {
                    return substr($value, 5);
                }
                return $value;

            default:
                return $value;
        }
    }

    /**
     * Превращает запись репозитория в фактический --repo для restic.
     *
     * @param array<string, mixed> $repo
     */
    public static function toResticLocation(array $repo): string
    {
        $type = (string) ($repo['type'] ?? 'local');

        switch ($type) {
            case 's3':
                $bucket = (string) ($repo['s3_bucket'] ?? $repo['path'] ?? '');
                if ($bucket === '' || str_starts_with($bucket, 's3:')) {
                    return $bucket;
                }
                $endpoint = trim((string) ($repo['env']['AWS_ENDPOINT'] ?? ''));
                if ($endpoint === '') {
                    return 's3:s3.amazonaws.com/' . ltrim($bucket, '/');
                }
                if (!str_contains($endpoint, '://')) {
                    $endpoint = 'https://' . $endpoint;
                }
                return 's3:' . rtrim($endpoint, '/') . '/' . ltrim($bucket, '/');

            case 'sftp':
                $value = (string) ($repo['sftp_path'] ?? $repo['path'] ?? '');
                if (str_starts_with($value, 'sftp:')) {
                    return $value;
                }
                return 'sftp:' . $value;

            case 'rest':
                $value = (string) ($repo['rest_url'] ?? $repo['path'] ?? '');
                if (str_starts_with($value, 'rest:')) {
                    return $value;
                }
                return 'rest:' . $value;

            default:
                return (string) ($repo['local_path'] ?? $repo['path'] ?? '');
        }
    }

    /**
     * Проверяет принадлежность пути разрешённым корням.
     * Точное совпадение с корнем или путь внутри корня. Корень "/" разрешает всё.
     * Пустой список корней = ограничение отключено.
     *
     * @param array<int, string> $roots
     */
    public static function isWithinRoots(string $path, array $roots): bool
    {
        if ($roots === []) {
            return true;
        }

        foreach ($roots as $root) {
            $root = rtrim((string) $root, '/');
            if ($root === '') {
                return true;
            }
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает первый путь вне разрешённых корней или null.
     *
     * @param array<int, string> $paths
     * @param array<int, string> $roots
     */
    public static function firstDisallowedBackupPath(array $paths, array $roots): ?string
    {
        foreach ($paths as $path) {
            if (!self::isWithinRoots((string) $path, $roots)) {
                return (string) $path;
            }
        }

        return null;
    }

    /**
     * Проверяет, что локальный путь репозитория находится в разрешённых корнях.
     *
     * @param array<int, string> $roots
     */
    public static function localRepoAllowed(string $path, array $roots): bool
    {
        return self::isWithinRoots($path, $roots);
    }
}
