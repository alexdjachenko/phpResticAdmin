<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Cache;

/**
 * Контракт кеша одной области (context-bound).
 *
 * Получается из CacheManager::for(CacheScope): уровень кеша выбирается в точке,
 * где известен контекст объекта. Потребитель уже не думает, где и чем значение
 * хранится — интерфейс один, механизм подставляется менеджером.
 */
interface CacheInterface
{
    /**
     * Значение или null, если записи нет либо она старше $ttl.
     *
     * Просроченная запись при чтении НЕ удаляется: служебные поля (метка
     * задачи, ошибка) нужны вызывающему уровню, чтобы отличить «данные
     * устарели» от «данных нет».
     */
    public function get(string $key, ?int $ttl = null): mixed;

    /**
     * Запись целиком: значение + время + признак просрочки.
     *
     * @return array{value: mixed, cached_at: int, stale: bool}|null
     */
    public function entry(string $key, ?int $ttl = null): ?array;

    /**
     * Записывает значение, проставляя cached_at = time().
     */
    public function set(string $key, mixed $value): void;

    /**
     * Удаляет запись.
     */
    public function remove(string $key): void;

    /**
     * get() ?? set(producer()) — единственный «умный» метод контракта.
     */
    public function remember(string $key, ?int $ttl, callable $producer): mixed;
}
