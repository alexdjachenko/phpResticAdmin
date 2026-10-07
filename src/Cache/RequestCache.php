<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Cache;

/**
 * Драйвер области текущего исполнения.
 *
 * Хранит значения в памяти процесса до конца HTTP-запроса. Используется для
 * мемоизации горячего пути (список задач tsp, настройки, базовое окружение):
 * посчитать один раз за запрос.
 */
class RequestCache implements CacheInterface
{
    /** @var array<string, array{cached_at: int, value: mixed}> */
    private array $store = [];

    public function get(string $key, ?int $ttl = null): mixed
    {
        $entry = $this->entry($key, $ttl);

        if ($entry === null || $entry['stale']) {
            return null;
        }

        return $entry['value'];
    }

    public function entry(string $key, ?int $ttl = null): ?array
    {
        if (!array_key_exists($key, $this->store)) {
            return null;
        }

        $raw = $this->store[$key];
        $cachedAt = $raw['cached_at'];

        return [
            'value' => $raw['value'],
            'cached_at' => $cachedAt,
            'stale' => $ttl !== null && (time() - $cachedAt) > $ttl,
        ];
    }

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = ['cached_at' => time(), 'value' => $value];
    }

    public function remove(string $key): void
    {
        unset($this->store[$key]);
    }

    public function remember(string $key, ?int $ttl, callable $producer): mixed
    {
        $entry = $this->entry($key, $ttl);
        if ($entry !== null && !$entry['stale']) {
            return $entry['value'];
        }

        $value = $producer();
        $this->set($key, $value);

        return $value;
    }

    /**
     * Полностью очищает область текущего запроса (для тестов и инвалидации).
     */
    public function clear(): void
    {
        $this->store = [];
    }
}
