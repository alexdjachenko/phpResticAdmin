<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Core\App;
use App\Core\Session;

/**
 * Кеш списка снепшотов в сессии (на TTL из settings `snapshot_cache_ttl`).
 *
 * Помимо самих данных хранит метку фоновой tsp-задачи, которая сейчас
 * загружает список. Это позволяет странице списка не запускать повторную
 * задачу, пока предыдущая ещё выполняется.
 */
class SnapshotCacheStorage
{
    private const CACHE_PREFIX = 'snapshot_cache_';
    private const TASK_PREFIX = 'snapshot_task_';

    private Session $session;
    private int $ttl;

    public function __construct(?Session $session = null, ?int $ttl = null)
    {
        $this->session = $session ?? App::session();

        if ($ttl === null) {
            $settings = App::configStorage()->loadSettings();
            $ttl = (int) ($settings['snapshot_cache_ttl'] ?? 600);
        }
        $this->ttl = $ttl;
    }

    /**
     * Свежий кеш списка снепшотов или null.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function get(string $repoId): ?array
    {
        $entry = $this->session->get(self::CACHE_PREFIX . $repoId);

        if (!is_array($entry)) {
            return null;
        }

        $cachedAt = $entry['cached_at'] ?? null;
        if (!is_int($cachedAt) || (time() - $cachedAt) > $this->ttl) {
            $this->session->remove(self::CACHE_PREFIX . $repoId);
            return null;
        }

        return $entry['snapshots'] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     */
    public function set(string $repoId, array $snapshots): void
    {
        $this->session->set(self::CACHE_PREFIX . $repoId, [
            'cached_at' => time(),
            'snapshots' => $snapshots,
        ]);
    }

    public function invalidate(string $repoId): void
    {
        $this->session->remove(self::CACHE_PREFIX . $repoId);
    }

    public function taskLabel(string $repoId): ?string
    {
        $label = $this->session->get(self::TASK_PREFIX . $repoId);
        return is_string($label) && $label !== '' ? $label : null;
    }

    public function setTaskLabel(string $repoId, string $label): void
    {
        $this->session->set(self::TASK_PREFIX . $repoId, $label);
    }

    public function clearTaskLabel(string $repoId): void
    {
        $this->session->remove(self::TASK_PREFIX . $repoId);
    }
}
