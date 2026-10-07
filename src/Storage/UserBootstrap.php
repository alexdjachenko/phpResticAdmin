<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use Symfony\Component\Yaml\Yaml;

/**
 * Автосоздание пользователя admin2 при первом старте контейнера.
 *
 * Если users.yaml отсутствует — создаёт его с учёткой admin2 (полные права,
 * can_manage_users + can_manage_processes) и случайным паролем. Пароль
 * возвращается в открытом виде, чтобы entrypoint напечатал его в лог.
 */
class UserBootstrap
{
    /**
     * @return string|null пароль admin2, если пользователь был создан; null — если файл уже есть
     */
    public function ensureAdmin2(string $usersFile): ?string
    {
        if (file_exists($usersFile)) {
            return null;
        }

        $password = bin2hex(random_bytes(12));

        $users = [
            'admin2' => [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'api_tokens' => [],
                'can_init' => true,
                'can_delete' => true,
                'can_manage_users' => true,
                'can_manage_processes' => true,
                'repos' => [
                    'public'  => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
                    'private' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
                    'session' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
                ],
            ],
        ];

        $dir = dirname($usersFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($usersFile, Yaml::dump($users, 4, 2));

        return $password;
    }
}
