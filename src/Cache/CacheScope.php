<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Cache;

/**
 * Контекст кеша.
 *
 * Уровень выбирается в точке, где известен контекст объекта (например, по
 * категории репозитория), а не закрепляется за классом хранилища. Система
 * многопользовательская, поэтому данные разного происхождения нельзя хранить
 * в одном месте:
 *
 * - System  — общие для всех пользователей и сессий данные (доступно только
 *             то, что безопасно разделять между пользователями).
 * - User    — данные, привязанные к браузеру/сессии пользователя.
 * - Request — мемоизация в пределах одного HTTP-запроса.
 */
enum CacheScope: string
{
    case System = 'system';
    case User = 'user';
    case Request = 'request';
}
