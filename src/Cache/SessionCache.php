<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Cache;

use App\Core\App;
use App\Core\Session;

/**
 * Драйвер кеша поверх сессии.
 *
 * Хранит значения в $_SESSION под ключом `cache_<namespace>_<key>`, где
 * namespace разводит контексты (например, 'user' и 'system'), чтобы записи
 * разных областей не пересекались.
 *
 * Если сессия не активна (CLI, REST), область молча деградирует: get()
 * возвращает null, set()/remove() — no-op. Это позволяет коду, случайно
 * обратившемуся к кешу без сессии, не падать.
 */
class SessionCache implements CacheInterface
{
    private string $namespace;
    private Session $session;

    public function __construct(string $namespace, ?Session $session = null)
    {
        $this->namespace = $namespace;
        $this->session = $session ?? App::session();
    }

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
        if (!$this->isActive()) {
            return null;
        }

        $raw = $this->session->get($this->storageKey($key));
        if (!is_array($raw) || !array_key_exists('cached_at', $raw) || !array_key_exists('value', $raw)) {
            return null;
        }

        $cachedAt = (int) $raw['cached_at'];

        return [
            'value' => $raw['value'],
            'cached_at' => $cachedAt,
            'stale' => $ttl !== null && (time() - $cachedAt) > $ttl,
        ];
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->isActive()) {
            return;
        }

        $this->session->set($this->storageKey($key), [
            'cached_at' => time(),
            'value' => $value,
        ]);
    }

    public function remove(string $key): void
    {
        if (!$this->isActive()) {
            return;
        }

        $this->session->remove($this->storageKey($key));
    }

    public function remember(string $key, ?int $ttl, callable $producer): mixed
    {
        $value = $this->get($key, $ttl);
        if ($value !== null) {
            return $value;
        }

        $value = $producer();
        $this->set($key, $value);

        return $value;
    }

    private function storageKey(string $key): string
    {
        return 'cache_' . $this->namespace . '_' . $key;
    }

    private function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
