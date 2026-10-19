<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Cache\CacheManager;

/**
 * Доменная обёртка над кеш-слоем для производных от restic данных.
 *
 * В одной записи целевого объекта (ContentTarget) живут и контент, и обещание:
 *
 *   {
 *     value:   <контент> | null,   // готовые данные
 *     promise: <label>   | null,   // метка tsp-задачи, которая их наполнит
 *     error:   <string>  | null,   // липкая ошибка (повтор только по кнопке)
 *     meta:    {started_at|duration} | null
 *   }
 *
 * Область кеша задаёт сам целевой объект (System — для публичных репозиториев и
 * статистики, User — для приватных/сессионных), поэтому утечка между
 * пользователями невозможна.
 */
class ContentCacheStorage
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

    /**
     * TTL записи для целевого объекта (статистика неизменяема — TTL большой).
     */
    public function ttlFor(ContentTarget $target): ?int
    {
        return $target->kind === ContentTarget::KIND_SNAPSHOT_STATS ? $this->statsTtl : $this->listTtl;
    }

    /**
     * Запись целиком (значение + обещание + ошибка + метаданные).
     */
    public function read(ContentTarget $target): ?ContentEntry
    {
        $entry = $this->cache->for($target->scope)->entry($target->cacheKey(), $this->ttlFor($target));
        if ($entry === null) {
            return null;
        }

        $payload = is_array($entry['value']) ? $entry['value'] : [];

        $promise = $payload['promise'] ?? null;
        $error = $payload['error'] ?? null;
        $meta = $payload['meta'] ?? null;

        return new ContentEntry(
            $payload['value'] ?? null,
            is_string($promise) && $promise !== '' ? $promise : null,
            is_string($error) && $error !== '' ? $error : null,
            (int) $entry['cached_at'],
            is_array($meta) ? $meta : null,
            (bool) $entry['stale'],
        );
    }

    /**
     * Свежее значение или null (если записи нет, она просрочена или пуста).
     */
    public function value(ContentTarget $target): mixed
    {
        $entry = $this->read($target);

        if ($entry === null || $entry->stale || $entry->value === null) {
            return null;
        }

        return $entry->value;
    }

    /**
     * Записывает готовый контент (снимает обещание и ошибку).
     */
    public function put(ContentTarget $target, mixed $value, ?array $meta = null): void
    {
        $this->cache->for($target->scope)->set($target->cacheKey(), [
            'value' => $value,
            'promise' => null,
            'error' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * Помечает, что контент наполняет фоновая задача (данные ещё не готовы).
     */
    public function promise(ContentTarget $target, string $label): void
    {
        $this->cache->for($target->scope)->set($target->cacheKey(), [
            'value' => null,
            'promise' => $label,
            'error' => null,
            'meta' => ['started_at' => time()],
        ]);
    }

    /**
     * Фиксирует липкую ошибку (повтор только по кнопке).
     */
    public function fail(ContentTarget $target, string $error, ?string $label = null): void
    {
        $this->cache->for($target->scope)->set($target->cacheKey(), [
            'value' => null,
            'promise' => $label,
            'error' => $error,
            'meta' => null,
        ]);
    }

    /**
     * Убирает запись (инвалидация/повтор).
     */
    public function clear(ContentTarget $target): void
    {
        $this->cache->for($target->scope)->remove($target->cacheKey());
    }
}
