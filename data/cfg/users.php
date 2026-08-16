<?php
// Generate a proper password hash with: php -r "echo password_hash('admin', PASSWORD_DEFAULT);"
//
// Пароль пользователя (общая схема для ВСЕХ пользователей, php и yaml):
//   password_var — ИМЯ переменной/секрета, в которой лежит bcrypt-хеш пароля.
//   Порядок разрешения: Docker-secret /run/secrets/<name> → getenv(<name>)
//   → поле password учётки. Значение переменной — bcrypt-хеш (не открытый пароль).
//   Для учётки admin используется дефолтное имя PHPRESTICADMIN_ADMIN_PASSWORD_HASH;
//   остальные пользователи задают произвольное имя сами.
//   Плейсхолдер-хеш admin намеренно неверный: без переменной/секрета вход невозможен.
//
// Формат секции repos:
//   use       — видимость репозитория в интерфейсе, базовые мета-данные (старый ключ)
//   use_read  — чтение контента: browse, download, export, список ключей (новый ключ)
//   use_write — запись в restic: backup, tag, maintenance, keys (новый ключ, без fallback'ов)
//   edit      — CRUD записи о репозитории: имя, путь, пароль, удалить (старый ключ)
//
// Права независимы. Единственная импликация: use_write ⇒ use_read.
return [
    'admin' => [
        'password' => '$2y$10$A1b2C3d4E5f6G7h8I9j0K.L1m2N3o4P5q6R7s8T9u0V1w2X3y4Z5',
        'password_var' => 'PHPRESTICADMIN_ADMIN_PASSWORD_HASH',
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
    'guest' => [
        'password' => null,
        'api_tokens' => [],
        'can_init' => false,
        'can_delete' => false,
        'repos' => [
            'public'  => ['use' => true, 'use_read' => true,  'use_write' => false, 'edit' => false],
            'private' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
            'session' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
        ],
    ],
    // 'viewer' => [
    //     'password' => '$2y$10$...',
    //     'api_tokens' => [],
    //     'can_init' => false,
    //     'can_delete' => false,
    //     'repos' => [
    //         'public'  => ['use' => true, 'use_read' => true, 'use_write' => false, 'edit' => false],
    //         'private' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
    //         'session' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
    //     ],
    // ],
];
