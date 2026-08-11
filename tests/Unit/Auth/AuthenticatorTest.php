<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Auth;

use App\Auth\Authenticator;
use App\Core\Session;
use App\Storage\ConfigStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Authenticator (аутентификация и модель прав).
 *
 * Цель: проверить все аспекты аутентификации: resolve (гость/пользователь/null),
 *       login/logout, isGuest/isLoggedIn, и полную матрицу прав
 *       (canUse, canUseRead, canUseWrite, canEdit, canInit, canDelete, canMove)
 *       включая обратную совместимость с legacy-конфигурациями.
 *
 * Сценарий:
 *   - resolve: null без гостя и без входа, гость при guest_user, пользователь при входе.
 *   - login: успех с правильным паролем, провал с неправильным/неизвестным.
 *   - logout: очистка сессии.
 *   - Права: admin имеет все права, guest — ограниченные.
 *   - Legacy: пользователь без секции repos получает полные права.
 *   - Гранулярность: use_read не наследуется из use и edit.
 *   - Импликация: use_write ⇒ use_read.
 *   - canMove требует edit на обеих категориях.
 *
 * Критерий успеха: все assert проходят.
 */
class AuthenticatorTest extends TestCase
{
    /** @var string Временная директория для конфигов */
    private string $tmpDir;
    /** @var Session */
    private Session $session;
    /** @var ConfigStorage Хранилище конфигов (users.php, settings.php) */
    private ConfigStorage $configStorage;

    protected function setUp(): void
    {
        // Создаём временную директорию с тестовыми конфигами
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_auth_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        // Генерируем bcrypt-хеш пароля
        $passwordHash = password_hash('secret123', PASSWORD_DEFAULT);

        // Базовый конфиг: admin с полными правами, guest с ограниченными
        $usersConfig = [
            'admin' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'can_init' => true,
                'can_delete' => true,
                'repos' => [
                    'public' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
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
                    'public' => ['use' => true, 'edit' => false],
                    'private' => ['use' => false, 'edit' => false],
                    'session' => ['use' => false, 'edit' => false],
                ],
            ],
        ];
        $this->writeUsersConfig($this->tmpDir, $usersConfig);

        // settings.php: guest_user = null (гостевой доступ выключен)
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => null, "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );

        // Инициализируем сессию
        $this->session = new Session();
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $this->session->set('_test', true);

        $this->configStorage = new ConfigStorage($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->session->destroy();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->removeDir($this->tmpDir);
    }

    // === resolve: определение текущего пользователя ===

    /** Без входа и без guest_user → resolve() возвращает null. */
    public function testResolveReturnsNullWhenNoAuthAndNoGuest(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $this->assertNull($auth->resolve());
    }

    /** С настроенным guest_user → resolve() возвращает гостя. */
    public function testResolveReturnsGuestUserWhenConfigured(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertSame('guest', $auth->resolve());
    }

    // === login / logout ===

    /** Успешный вход с правильным паролем. */
    public function testLoginSucceedsWithCorrectPassword(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('admin', 'secret123');
        $this->assertTrue($result);
        $this->assertTrue($auth->isLoggedIn());
        $this->assertSame('admin', $auth->user());
    }

    /** Вход с неправильным паролем → false, isLoggedIn = false. */
    public function testLoginFailsWithWrongPassword(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('admin', 'wrongpass');
        $this->assertFalse($result);
        $this->assertFalse($auth->isLoggedIn());
    }

    /** Вход с несуществующим пользователем → false. */
    public function testLoginFailsWithUnknownUser(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('unknown', 'secret123');
        $this->assertFalse($result);
    }

    /** logout очищает сессию. */
    public function testLogoutClearsSession(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->isLoggedIn());

        $auth->logout();
        $this->assertFalse($auth->isLoggedIn());
    }

    // === isGuest ===

    /** Гость определяется как isGuest = true. */
    public function testIsGuestReturnsTrueForGuestUser(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertTrue($auth->isGuest());
    }

    /** Вошедший пользователь — не гость. */
    public function testIsGuestReturnsFalseForLoggedInUser(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertFalse($auth->isGuest());
    }

    // === Права: canUse, canEdit, canMove ===

    /** Admin имеет canUse на всех категориях. */
    public function testCanUseReturnsTrueForAllowedCategory(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canUse('public'));
        $this->assertTrue($auth->canUse('private'));
        $this->assertTrue($auth->canUse('session'));
    }

    /** Гость: canUse('public') = true, canEdit('public') = false, private = false. */
    public function testCanEditReturnsFalseForUseOnlyCategory(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertTrue($auth->canUse('public'));
        $this->assertFalse($auth->canEdit('public'));
        $this->assertFalse($auth->canUse('private'));
        $this->assertFalse($auth->canEdit('private'));
    }

    /** canMove требует edit на обеих категориях. */
    public function testCanMoveRequiresEditOnBothCategories(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canMove('public', 'private'));
        $this->assertTrue($auth->canMove('private', 'session'));
    }

    /** Гость не может перемещать репозитории. */
    public function testGuestCannotMove(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertFalse($auth->canMove('public', 'private'));
        $this->assertFalse($auth->canMove('public', 'public'));
    }

    // === Legacy / fallback ===

    /** Legacy-пользователь без секции repos получает полные права. */
    public function testFallbackFullRightsForLegacyUser(): void
    {
        $passwordHash = password_hash('legacy', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'legacy' => ['password' => $passwordHash],
        ]);

        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('legacy', 'legacy');

        $this->assertTrue($auth->canUse('public'));
        $this->assertTrue($auth->canEdit('public'));
        $this->assertTrue($auth->canUse('private'));
        $this->assertTrue($auth->canEdit('private'));
        // Без явных can_init/can_delete → true (isLoggedIn = true)
        $this->assertTrue($auth->canInit());
        $this->assertTrue($auth->canDelete());
    }

    /** Гость без секции repos: публичные — только use, без use_read/use_write/edit. */
    public function testGuestDefaultRights(): void
    {
        $this->writeUsersConfig($this->tmpDir, [
            'guest' => ['password' => null],
        ]);
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );

        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertTrue($auth->canUse('public'));
        $this->assertFalse($auth->canEdit('public'));
        $this->assertFalse($auth->canUse('private'));
        $this->assertFalse($auth->canEdit('private'));
        // Гость не может init/delete
        $this->assertFalse($auth->canInit());
        $this->assertFalse($auth->canDelete());
    }

    // === canInit / canDelete ===

    /** Admin может init и delete. */
    public function testCanInitReturnsTrueForAdmin(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canInit());
    }

    /** Гость не может init. */
    public function testGuestCannotInit(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertFalse($auth->canInit());
    }

    /** Admin может delete. */
    public function testCanDeleteReturnsTrueForAdmin(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canDelete());
    }

    /** Гость не может delete. */
    public function testGuestCannotDelete(): void
    {
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => "guest", "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);

        $this->assertFalse($auth->canDelete());
    }

    // === canUseRead / canUseWrite (гранулярные права) ===

    /** Admin имеет canUseRead на всех категориях. */
    public function testCanUseReadReturnsTrue(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canUseRead('public'));
        $this->assertTrue($auth->canUseRead('private'));
        $this->assertTrue($auth->canUseRead('session'));
    }

    /** canUseWrite работает только где явно задан. */
    public function testCanUseWriteTrueWhenExplicitlySet(): void
    {
        $passwordHash = password_hash('writer', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'writer' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'repos' => [
                    'public' => ['use_read' => true, 'use_write' => true, 'edit' => false],
                    'private' => ['use_read' => false, 'use_write' => false, 'edit' => false],
                    'session' => ['use_read' => false, 'use_write' => false, 'edit' => false],
                ],
            ],
        ]);
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('writer', 'writer');

        $this->assertTrue($auth->canUseWrite('public'));
        $this->assertFalse($auth->canUseWrite('private'));
    }

    /** Импликация: use_write ⇒ use_read (use_read можно не задавать явно). */
    public function testCanUseWriteImpliesCanUseRead(): void
    {
        $passwordHash = password_hash('writer', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'writer' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'repos' => [
                    'public' => ['use_write' => true, 'edit' => false],
                    'private' => ['use_read' => false, 'use_write' => false, 'edit' => false],
                    'session' => ['use_read' => false, 'use_write' => false, 'edit' => false],
                ],
            ],
        ]);
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('writer', 'writer');

        // use_write=true и use_read не задан → use_read = true (авто)
        $this->assertTrue($auth->canUseRead('public'));
        $this->assertTrue($auth->canUseWrite('public'));
    }

    /** use_read НЕ наследуется из edit. */
    public function testUseReadDoesNotFallBackToEdit(): void
    {
        $passwordHash = password_hash('editor', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'editor' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'repos' => [
                    'public' => ['edit' => true],
                    'private' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
                    'session' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
                ],
            ],
        ]);
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('editor', 'editor');

        // edit=true НЕ даёт use_read
        $this->assertTrue($auth->canEdit('public'));
        $this->assertFalse($auth->canUseRead('public'));
    }

    /** use_read НЕ наследуется из старого ключа use. */
    public function testUseReadDoesNotFallBackToOldUseKey(): void
    {
        $passwordHash = password_hash('legacy', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'legacy' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'repos' => [
                    'public' => ['use' => true, 'edit' => false],
                    'private' => ['use' => false, 'edit' => false],
                    'session' => ['use' => false, 'edit' => false],
                ],
            ],
        ]);
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('legacy', 'legacy');

        // Старый ключ use даёт только видимость, НЕ чтение контента
        $this->assertTrue($auth->canUse('public'));
        $this->assertFalse($auth->canUseRead('public'));
    }

    /** Legacy-конфиг (use/edit) НЕ даёт use_write. */
    public function testLegacyAdminHasNoUseWrite(): void
    {
        $passwordHash = password_hash('legacy', PASSWORD_DEFAULT);
        $this->writeUsersConfig($this->tmpDir, [
            'legacy' => [
                'password' => $passwordHash,
                'api_tokens' => [],
                'repos' => [
                    'public' => ['use' => true, 'edit' => true],
                    'private' => ['use' => true, 'edit' => true],
                    'session' => ['use' => true, 'edit' => true],
                ],
            ],
        ]);
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('legacy', 'legacy');

        // use_write не задан явно → false (без fallback'а)
        $this->assertFalse($auth->canUseWrite('public'));
        $this->assertFalse($auth->canUseWrite('private'));
        $this->assertFalse($auth->canUseWrite('session'));
    }

    /**
     * Записывает PHP-конфиг users.php через var_export.
     * @param string $dir Директория для сохранения
     * @param array $users Массив пользователей
     */
    private function writeUsersConfig(string $dir, array $users): void
    {
        file_put_contents(
            $dir . '/users.php',
            '<?php return ' . var_export($users, true) . ';'
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
