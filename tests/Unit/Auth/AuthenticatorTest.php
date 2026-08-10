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

class AuthenticatorTest extends TestCase
{
    private string $tmpDir;
    private Session $session;
    private ConfigStorage $configStorage;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_auth_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $passwordHash = password_hash('secret123', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "admin" => [
                    "password" => ' . var_export($passwordHash, true) . ',
                    "api_tokens" => [],
                    "can_init" => true,
                    "can_delete" => true,
                    "repos" => [
                        "public" => ["use" => true, "use_read" => true, "use_write" => true, "edit" => true],
                        "private" => ["use" => true, "use_read" => true, "use_write" => true, "edit" => true],
                        "session" => ["use" => true, "use_read" => true, "use_write" => true, "edit" => true],
                    ],
                ],
                "guest" => [
                    "password" => null,
                    "api_tokens" => [],
                    "can_init" => false,
                    "can_delete" => false,
                    "repos" => [
                        "public" => ["use" => true, "edit" => false],
                        "private" => ["use" => false, "edit" => false],
                        "session" => ["use" => false, "edit" => false],
                    ],
                ],
            ];'
        );
        file_put_contents(
            $this->tmpDir . '/settings.php',
            '<?php return ["guest_user" => null, "tmp_dir" => "/tmp", "log_dir" => "/var/log", "timezone" => "UTC"];'
        );

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

    public function testResolveReturnsNullWhenNoAuthAndNoGuest(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $this->assertNull($auth->resolve());
    }

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

    public function testLoginSucceedsWithCorrectPassword(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('admin', 'secret123');
        $this->assertTrue($result);
        $this->assertTrue($auth->isLoggedIn());
        $this->assertSame('admin', $auth->user());
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('admin', 'wrongpass');
        $this->assertFalse($result);
        $this->assertFalse($auth->isLoggedIn());
    }

    public function testLoginFailsWithUnknownUser(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);

        $result = $auth->login('unknown', 'secret123');
        $this->assertFalse($result);
    }

    public function testLogoutClearsSession(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->isLoggedIn());

        $auth->logout();
        $this->assertFalse($auth->isLoggedIn());
    }

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

    public function testIsGuestReturnsFalseForLoggedInUser(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertFalse($auth->isGuest());
    }

    public function testCanUseReturnsTrueForAllowedCategory(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canUse('public'));
        $this->assertTrue($auth->canUse('private'));
        $this->assertTrue($auth->canUse('session'));
    }

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

    public function testCanMoveRequiresEditOnBothCategories(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canMove('public', 'private'));
        $this->assertTrue($auth->canMove('private', 'session'));
    }

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

    public function testFallbackFullRightsForLegacyUser(): void
    {
        $passwordHash = password_hash('legacy', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "legacy" => ["password" => ' . var_export($passwordHash, true) . '],
            ];'
        );

        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('legacy', 'legacy');

        $this->assertTrue($auth->canUse('public'));
        $this->assertTrue($auth->canEdit('public'));
        $this->assertTrue($auth->canUse('private'));
        $this->assertTrue($auth->canEdit('private'));
        // Legacy user gets init/delete because isLoggedIn() is true and no explicit can_init/can_delete
        $this->assertTrue($auth->canInit());
        $this->assertTrue($auth->canDelete());
    }

    public function testGuestDefaultRights(): void
    {
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "guest" => ["password" => null],
            ];'
        );
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
        // Guest without explicit can_init/can_delete defaults to false (not logged in)
        $this->assertFalse($auth->canInit());
        $this->assertFalse($auth->canDelete());
    }

    public function testCanInitReturnsTrueForAdmin(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canInit());
    }

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

    public function testCanDeleteReturnsTrueForAdmin(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canDelete());
    }

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

    public function testCanUseReadReturnsTrue(): void
    {
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertTrue($auth->canUseRead('public'));
        $this->assertTrue($auth->canUseRead('private'));
        $this->assertTrue($auth->canUseRead('session'));
    }

    public function testCanUseWriteTrueWhenExplicitlySet(): void
    {
        $passwordHash = password_hash('writer', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "writer" => [
                    "password" => ' . var_export($passwordHash, true) . ',
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["use_read" => true, "use_write" => true, "edit" => false],
                        "private" => ["use_read" => false, "use_write" => false, "edit" => false],
                        "session" => ["use_read" => false, "use_write" => false, "edit" => false],
                    ],
                ],
            ];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('writer', 'writer');

        $this->assertTrue($auth->canUseWrite('public'));
        $this->assertFalse($auth->canUseWrite('private'));
    }

    public function testCanUseWriteImpliesCanUseRead(): void
    {
        $passwordHash = password_hash('writer', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "writer" => [
                    "password" => ' . var_export($passwordHash, true) . ',
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["use_write" => true, "edit" => false],
                        "private" => ["use_read" => false, "use_write" => false, "edit" => false],
                        "session" => ["use_read" => false, "use_write" => false, "edit" => false],
                    ],
                ],
            ];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('writer', 'writer');

        // use_write=true and use_read not set → use_read auto-true
        $this->assertTrue($auth->canUseRead('public'));
        $this->assertTrue($auth->canUseWrite('public'));
    }

    public function testUseReadDoesNotFallBackToEdit(): void
    {
        $passwordHash = password_hash('editor', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "editor" => [
                    "password" => ' . var_export($passwordHash, true) . ',
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["edit" => true],
                        "private" => ["use" => false, "use_read" => false, "use_write" => false, "edit" => false],
                        "session" => ["use" => false, "use_read" => false, "use_write" => false, "edit" => false],
                    ],
                ],
            ];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('editor', 'editor');

        // use_read is independent: edit does NOT give use_read
        $this->assertTrue($auth->canEdit('public'));
        $this->assertFalse($auth->canUseRead('public'));
    }

    public function testUseReadDoesNotFallBackToOldUseKey(): void
    {
        $passwordHash = password_hash('legacy', PASSWORD_DEFAULT);
        file_put_contents(
            $this->tmpDir . '/users.php',
            '<?php return [
                "legacy" => [
                    "password" => ' . var_export($passwordHash, true) . ',
                    "api_tokens" => [],
                    "repos" => [
                        "public" => ["use" => true, "edit" => false],
                        "private" => ["use" => false, "edit" => false],
                        "session" => ["use" => false, "edit" => false],
                    ],
                ],
            ];'
        );
        $configStorage = new ConfigStorage($this->tmpDir);
        $auth = new Authenticator($configStorage, $this->session);
        $auth->login('legacy', 'legacy');

        // Old 'use' key only gives visibility, NOT content reading
        $this->assertTrue($auth->canUse('public'));
        $this->assertFalse($auth->canUseRead('public'));
    }

    public function testLegacyAdminHasNoUseWrite(): void
    {
        // Verify that the existing admin config (with old keys) does NOT get use_write
        // This confirms use_write has no fallback
        $auth = new Authenticator($this->configStorage, $this->session);
        $auth->login('admin', 'secret123');

        $this->assertFalse($auth->canUseWrite('public'));
        $this->assertFalse($auth->canUseWrite('private'));
        $this->assertFalse($auth->canUseWrite('session'));
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
