<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Cache;

use App\Core\App;

/**
 * Точка подмены механизма кеширования.
 *
 * Единственная роль менеджера — по запрошенному контексту (CacheScope) выдать
 * CacheInterface. Уровень кеша выбирает вызывающий код там, где известен
 * контекст объекта (CacheScope::System для публичного, CacheScope::User для
 * приватного/сессионного, CacheScope::Request для мемоизации на запрос).
 *
 * Смена механизма (сессия → файл/redis) выполняется здесь, без правки точек
 * вызова. Сейчас реализованы драйверы памяти (Request) и сессии (User/System);
 * настройка `cache_driver` задаёт драйвер системной области на будущее.
 */
final class CacheManager
{
    private ?string $driverOverride;

    private ?RequestCache $request = null;
    private ?SessionCache $user = null;
    private ?SessionCache $system = null;

    /**
     * @param string|null $driverOverride имя драйвера системной области
     *        (для тестов; null — берётся из настроек `cache_driver`)
     */
    public function __construct(?string $driverOverride = null)
    {
        $this->driverOverride = $driverOverride;
    }

    /**
     * Возвращает область кеша для указанного контекста.
     */
    public function for(CacheScope $scope): CacheInterface
    {
        return match ($scope) {
            CacheScope::Request => $this->request(),
            CacheScope::User => $this->user(),
            CacheScope::System => $this->system(),
        };
    }

    /**
     * Область текущего запроса: один и тот же инстанс на весь запрос.
     */
    public function request(): RequestCache
    {
        if ($this->request === null) {
            $this->request = new RequestCache();
        }

        return $this->request;
    }

    /**
     * Пользовательская область (сессионная). Без активной сессии деградирует.
     */
    public function user(): SessionCache
    {
        if ($this->user === null) {
            $this->user = new SessionCache('user');
        }

        return $this->user;
    }

    /**
     * Системная область (общая для всех пользователей).
     *
     * Пока нет общего хранилища (файл/redis), область деградирует до
     * сессионной (per-client) — это безопасный дефолт: чужому пользователю
     * ничего не утечёт. Драйвер задаётся настройкой `cache_driver`.
     */
    public function system(): SessionCache
    {
        if ($this->system === null) {
            $this->resolveDriver();
            $this->system = new SessionCache('system');
        }

        return $this->system;
    }

    /**
     * Разрешает имя драйвера системной области.
     *
     * Неизвестное/пустое значение настройки не должно ломать приложение:
     * падаем на сессионный драйвер и пишем ошибку уровня 0.
     */
    private function resolveDriver(): string
    {
        $driver = $this->driverOverride;

        if ($driver === null) {
            $settings = App::configStorage()->loadSettings();
            $value = $settings['cache_driver'] ?? '';
            $driver = is_string($value) && $value !== '' ? $value : 'session';
        }

        if ($driver !== 'session') {
            App::log('cache[system] unknown driver "' . $driver . '", falling back to session', 0);
            return 'session';
        }

        return $driver;
    }
}
