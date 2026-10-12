<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Cache\CacheScope;

/**
 * Целевой объект производного контента (то, что лежит в кеше).
 *
 * Единая форма для всех тяжёлых операций: список снепшотов репозитория и
 * полная статистика одного снепшота. Объект описывает САМ КОНТЕНТ (ключ, область
 * кеша, операцию tsp), а не конкретную страницу. Из объекта выводится метка
 * задачи (см. ContentTargets::labelOf), а из метки — обратно объект
 * (ContentTargets::fromLabel): так обе стороны «метка <-> кеш» выводимы.
 *
 * `objectId` — субъект контента: id репозитория для списка снепшотов и id
 * снепшота для статистики. `repoId` — репозиторий операции (нужен для метки и
 * дедупликации задач); для списка снепшотов он совпадает с `objectId`.
 */
final class ContentTarget
{
    public const KIND_REPO_SNAPSHOTS = 'repo_snapshots';
    public const KIND_SNAPSHOT_STATS = 'snapshot_stats';

    public function __construct(
        public readonly string $kind,
        public readonly string $objectId,
        public readonly string $repoId,
        public readonly CacheScope $scope,
    ) {
    }

    /**
     * Код операции в метке задачи (TaskLabel::OPS).
     */
    public function op(): string
    {
        return $this->kind === self::KIND_SNAPSHOT_STATS ? 'snapstats' : 'snapshots';
    }

    /**
     * Ключ записи в области кеша.
     */
    public function cacheKey(): string
    {
        return $this->kind === self::KIND_SNAPSHOT_STATS
            ? 'snapshots/' . $this->objectId . '/stats.json'
            : 'repos/' . $this->objectId . '/snapshots.json';
    }
}
