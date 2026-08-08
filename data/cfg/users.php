<?php
// Generate a proper password hash with: php -r "echo password_hash('admin', PASSWORD_DEFAULT);"
return [
    'admin' => [
        'password' => '$2y$10$A1b2C3d4E5f6G7h8I9j0K.L1m2N3o4P5q6R7s8T9u0V1w2X3y4Z5',
        'api_tokens' => [],
        'repos' => [
            'public'  => ['use' => true, 'edit' => true],
            'private' => ['use' => true, 'edit' => true],
            'session' => ['use' => true, 'edit' => true],
        ],
    ],
    'guest' => [
        'password' => null,
        'api_tokens' => [],
        'repos' => [
            'public'  => ['use' => true,  'edit' => false],
            'private' => ['use' => false, 'edit' => false],
            'session' => ['use' => false, 'edit' => false],
        ],
    ],
    // 'viewer' => [
    //     'password' => '$2y$10$...',
    //     'api_tokens' => [],
    //     'repos' => [
    //         'public'  => ['use' => true, 'edit' => false],
    //         'private' => ['use' => false, 'edit' => false],
    //         'session' => ['use' => false, 'edit' => false],
    //     ],
    // ],
];
