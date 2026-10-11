<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Cache\CacheScope;
use App\Process\TaskLabel;

/**
 * Резолвер целевых объектов контента: превращает «репозиторий/снепшот» в
 * ContentTarget и обратно — из метки tsp-задачи в ContentTarget.
 *
 * Именно здесь фиксируется правило выбора области кеша по категории объекта:
 * данные публичного репозитория безопасно держать в системной области, данные
 * приватного/сессионного — только в пользовательской. Статистика снепшота
 * глобальна (id снепшота неизменяем), поэтому всегда системная.
 */
final class ContentTargets
{
    /**
     * @param array<string, mixed> $repo
     */
    public static function forSnapshots(array $repo): ContentTarget
    {
        $repoId = (string) ($repo['id'] ?? '');
        $scope = (($repo['category'] ?? 'public') === 'public') ? CacheScope::System : CacheScope::User;

        return new ContentTarget(ContentTarget::KIND_REPO_SNAPSHOTS, $repoId, $repoId, $scope);
    }

    /**
     * Целевой объект статистики снепшота (всегда системная область).
     */
    public static function forStats(string $repoId, string $snapId): ContentTarget
    {
        return new ContentTarget(ContentTarget::KIND_SNAPSHOT_STATS, $snapId, $repoId, CacheScope::System);
    }

    /**
     * Восстанавливает целевой объект из метки задачи.
     *
     * Для `snapshots` область по умолчанию системная (метка не несёт категорию):
     * вызывающий код, знающий репозиторий, строит объект через forSnapshots() с
     * точной областью. Для `snapstats` областью всегда System, поэтому метки
     * достаточно.
     */
    public static function fromLabel(string $label): ?ContentTarget
    {
        $parsed = TaskLabel::parse($label);
        if ($parsed === null) {
            return null;
        }

        $repoId = (string) ($parsed['repoId'] ?? '');
        $objectId = $parsed['objectId'] ?? null;

        if ($parsed['op'] === 'snapstats') {
            if ($objectId === null || $objectId === '') {
                return null;
            }
            return self::forStats($repoId, $objectId);
        }

        if ($parsed['op'] === 'snapshots') {
            if ($repoId === '') {
                return null;
            }
            return new ContentTarget(ContentTarget::KIND_REPO_SNAPSHOTS, $repoId, $repoId, CacheScope::System);
        }

        return null;
    }

    /**
     * Строит метку задачи для целевого объекта (обратная операция к fromLabel).
     */
    public static function labelOf(ContentTarget $target, string $username): string
    {
        if ($target->kind === ContentTarget::KIND_SNAPSHOT_STATS) {
            return TaskLabel::build($username, 'snapstats', $target->repoId, $target->objectId);
        }

        return TaskLabel::build($username, 'snapshots', $target->repoId, null);
    }
}
