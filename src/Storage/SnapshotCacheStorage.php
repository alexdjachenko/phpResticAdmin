<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Cache\CacheManager;
use App\Cache\CacheScope;

/**
 * Доменная обёртка над кеш-слоем для производных от restic данных:
 * списка снепшотов репозитория и полной статистики одного снепшота.
 *
 * Уровень кеша выбирается здесь, в точке, где известен контекст объекта:
 * данные публичного репозитория можно хранить в системной области, данные
 * приватного/сессионного — только в пользовательской (иначе возможна утечка
 * между пользователями). Статистика снепшота глобальна (id снепшота
 * неизменяем), поэтому всегда системная.
 *
 * Данные хранятся в области под ключом `repos/<repoId>/snapshots.json`
 * (список) и `snapshots/<snapId>/stats.json` (статистика).
 */
class SnapshotCacheStorage
{
    private CacheManager $cache;
    private ?int $listTtl;
    private ?int $statsTtl;

    public function __construct(CacheManager $cache, ?int $listTtl = null, ?int $statsTtl = null)
    {
        $this->cache = $cache;
        $this->listTtl = $listTtl;
        $this->statsTtl = $statsTtl;
    }

    public function listTtl(): ?int
    {
        return $this->listTtl;
    }

    /**
     * Область кеша для данных репозитория.
     */
    public function scopeFor(array $repo): CacheScope
    {
        return ($repo['category'] ?? 'public') === 'public'
            ? CacheScope::System
            : CacheScope::User;
    }

    /**
     * Запись списка снепшотов целиком (включая признак просрочки).
     *
     * @return array{computed_at: int, task_label: ?string, error: ?string, snapshots: ?array, started_at: ?int, duration: ?int, stale: bool}|null
     */
    public function listEntry(array $repo): ?array
    {
        $entry = $this->cache->for($this->scopeFor($repo))->entry($this->listKey($repo), $this->listTtl);
        if ($entry === null) {
            return null;
        }

        $value = is_array($entry['value']) ? $entry['value'] : [];

        return [
            'computed_at' => $entry['cached_at'],
            'task_label' => $value['task_label'] ?? null,
            'error' => $value['error'] ?? null,
            'snapshots' => is_array($value['snapshots'] ?? null) ? $value['snapshots'] : null,
            'started_at' => isset($value['started_at']) ? (int) $value['started_at'] : null,
            'duration' => isset($value['duration']) ? (int) $value['duration'] : null,
            'stale' => $entry['stale'],
        ];
    }

    /**
     * Свежий список снепшотов или null (если записи нет либо она просрочена).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function list(array $repo): ?array
    {
        $entry = $this->listEntry($repo);

        if ($entry === null || $entry['stale'] || $entry['snapshots'] === null) {
            return null;
        }

        return $entry['snapshots'];
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     */
    public function setList(array $repo, array $snapshots, ?string $taskLabel = null, ?int $duration = null): void
    {
        $this->cache->for($this->scopeFor($repo))->set($this->listKey($repo), [
            'task_label' => $taskLabel,
            'error' => null,
            'snapshots' => $snapshots,
            'started_at' => null,
            'duration' => $duration,
        ]);
    }

    /**
     * Фиксирует ошибку загрузки списка (без авто-повтора на стороне автомата).
     */
    public function setListError(array $repo, string $error, ?string $taskLabel = null): void
    {
        $this->cache->for($this->scopeFor($repo))->set($this->listKey($repo), [
            'task_label' => $taskLabel,
            'error' => $error,
            'snapshots' => null,
            'started_at' => null,
            'duration' => null,
        ]);
    }

    /**
     * Помечает, что список загружает фоновая задача (данные ещё не готовы).
     */
    public function markTask(array $repo, string $taskLabel): void
    {
        $this->cache->for($this->scopeFor($repo))->set($this->listKey($repo), [
            'task_label' => $taskLabel,
            'error' => null,
            'snapshots' => null,
            'started_at' => time(),
            'duration' => null,
        ]);
    }

    public function invalidateList(array $repo): void
    {
        $this->cache->for($this->scopeFor($repo))->remove($this->listKey($repo));
    }

    /**
     * Полная статистика одного снепшота (системная область, глобальна по id).
     *
     * @return array<string, mixed>|null
     */
    public function stats(string $snapId): ?array
    {
        $value = $this->cache->for(CacheScope::System)->get($this->statsKey($snapId), $this->statsTtl);

        return is_array($value) ? $value : null;
    }

    /**
     * @return array{computed_at: int, stats: array<string, mixed>, stale: bool}|null
     */
    public function statsEntry(string $snapId): ?array
    {
        $entry = $this->cache->for(CacheScope::System)->entry($this->statsKey($snapId), $this->statsTtl);
        if ($entry === null || !is_array($entry['value'])) {
            return null;
        }

        return [
            'computed_at' => $entry['cached_at'],
            'stats' => $entry['value'],
            'stale' => $entry['stale'],
        ];
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function setStats(string $snapId, array $stats): void
    {
        $this->cache->for(CacheScope::System)->set($this->statsKey($snapId), $stats);
    }

    private function listKey(array $repo): string
    {
        return 'repos/' . (string) ($repo['id'] ?? '') . '/snapshots.json';
    }

    private function statsKey(string $snapId): string
    {
        return 'snapshots/' . $snapId . '/stats.json';
    }
}
