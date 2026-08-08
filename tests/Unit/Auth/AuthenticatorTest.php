<?php

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
                    "repos" => [
                        "public" => ["use" => true, "edit" => true],
                        "private" => ["use" => true, "edit" => true],
                        "session" => ["use" => true, "edit" => true],
                    ],
                ],
                "guest" => [
                    "password" => null,
                    "api_tokens" => [],
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
        // Start session in CLI mode for testing
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

        // admin has full rights
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

        // admin has edit on all categories
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
        // Create a user without repos section
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

        // Legacy user gets full rights
        $this->assertTrue($auth->canUse('public'));
        $this->assertTrue($auth->canEdit('public'));
        $this->assertTrue($auth->canUse('private'));
        $this->assertTrue($auth->canEdit('private'));
    }

    public function testGuestDefaultRights(): void
    {
        // Create users.php without repos section for guest
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

        // Guest without repos section gets default guest rights
        $this->assertTrue($auth->canUse('public'));
        $this->assertFalse($auth->canEdit('public'));
        $this->assertFalse($auth->canUse('private'));
        $this->assertFalse($auth->canEdit('private'));
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
