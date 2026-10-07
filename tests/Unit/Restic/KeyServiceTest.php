<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Cache\RequestCache;
use App\Restic\CommandRunner;
use App\Restic\KeyService;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест KeyService (управление ключами restic через мок CommandRunner).
 *
 * Цель: проверить контракт «какой пароль в какую команду», идентификацию ключа
 *       по `current`, дифф id для новых ключей, отказы до опасных вызовов.
 *
 * Сценарий:
 *   - verifyKey: `cat config`, таймаут 10, пароль в env.
 *   - identifyKey: ответ по current; null, когда ни один ключ не помечен.
 *   - listKeys: листинг с паролем реквизитов.
 *   - workingKeyId: null при пустом пароле; null при неверном пароле; id при совпадении.
 *   - addKey: duplicate без вызова `key add`; key_id из диффа списка.
 *   - removeKeyByPassword: отказ current_key ДО вызова `key remove`.
 *   - changePassword: RESTIC_PASSWORD = oldPassword; отказы same_password/password_taken.
 *
 * Критерий успеха: моки проверяют аргументы/env/stdin, сервис возвращает ожидаемое.
 */
class KeyServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $repo;
    private string $repoPath;

    protected function setUp(): void
    {
        $this->repoPath = '/tmp/test-repo';
        $this->repo = [
            'id' => 'test-repo',
            'name' => 'Test Repo',
            'type' => 'local',
            'path' => $this->repoPath,
            'password' => null,
        ];
    }

    private function service(CommandRunner $runner): KeyService
    {
        return new KeyService($runner, new RequestCache());
    }

    /** Извлекает подкоманду из argv. */
    private function subcommand(array $cmd): string
    {
        foreach (['key', 'cat', 'snapshots', 'stats'] as $token) {
            if (in_array($token, $cmd, true)) {
                $i = array_search($token, $cmd, true);
                return $token === 'key' ? ('key ' . ($cmd[$i + 1] ?? '')) : $token;
            }
        }
        return '';
    }

    /** verifyKey: cat config, таймаут 10, пароль в env. */
    public function testVerifyKeyUsesCatConfig(): void
    {
        $capturedCommand = null;
        $capturedEnv = null;
        $capturedTimeout = null;

        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($cmd, $env = [], $stdin = null, $timeout = null) use (&$capturedCommand, &$capturedEnv, &$capturedTimeout) {
                $capturedCommand = $cmd;
                $capturedEnv = $env;
                $capturedTimeout = $timeout;
                return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
            });

        $result = $this->service($mock)->verifyKey($this->repo, 'secret');

        $this->assertTrue($result['ok']);
        $this->assertContains('cat', $capturedCommand);
        $this->assertContains('config', $capturedCommand);
        $this->assertSame(10, $capturedTimeout);
        $this->assertSame('secret', $capturedEnv['RESTIC_PASSWORD']);
    }

    /** listKeys: листинг с паролем реквизитов. */
    public function testListKeysUsesRepoCredentials(): void
    {
        $capturedEnv = null;
        $repo = array_merge($this->repo, ['password' => 'repo-pass']);

        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($cmd, $env = []) use (&$capturedEnv) {
                $capturedEnv = $env;
                return ['exitCode' => 0, 'stdout' => '[{"id":"abc","current":true}]', 'stderr' => ''];
            });

        $keys = $this->service($mock)->listKeys($repo);

        $this->assertCount(1, $keys);
        $this->assertSame('repo-pass', $capturedEnv['RESTIC_PASSWORD']);
    }

    /** identifyKey: возвращает ключ с current=true. */
    public function testIdentifyKeyReturnsCurrentKey(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"k1","current":false},{"id":"k2","current":true}]', 'stderr' => '']);

        $identified = $this->service($mock)->identifyKey($this->repo, 'pw');

        $this->assertNotNull($identified);
        $this->assertSame('k2', $identified['id']);
    }

    /** identifyKey: null, когда ни один ключ не помечен current. */
    public function testIdentifyKeyReturnsNullWithoutCurrent(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"k1","current":false}]', 'stderr' => '']);

        $this->assertNull($this->service($mock)->identifyKey($this->repo, 'pw'));
    }

    /** workingKeyId: null при пустом пароле, без обращения к restic. */
    public function testWorkingKeyIdNullWithoutPassword(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->never())->method('run');

        $this->assertNull($this->service($mock)->workingKeyId($this->repo));
    }

    /** workingKeyId: null при неверном пароле; id при совпадении. */
    public function testWorkingKeyId(): void
    {
        $repo = array_merge($this->repo, ['password' => 'repo-pass']);

        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"worker","current":true}]', 'stderr' => '']);

        $this->assertSame('worker', $this->service($mock)->workingKeyId($repo));

        $mock2 = $this->createMock(CommandRunner::class);
        $mock2->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"x","current":false}]', 'stderr' => '']);

        $this->assertNull($this->service($mock2)->workingKeyId($repo));
    }

    /** addKey: duplicate определяется идентификацией, `key add` не вызывается. */
    public function testAddKeyRejectsDuplicate(): void
    {
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"k2","current":true}]', 'stderr' => '']);

        $result = $this->service($mock)->addKey($this->repo, 'existing');

        $this->assertFalse($result['ok']);
        $this->assertSame('duplicate', $result['error_code']);
        $this->assertSame('k2', $result['key_id']);
    }

    /** addKey: key_id берётся из диффа списка (id, которого не было). */
    public function testAddKeyDiffReturnsNewId(): void
    {
        $calls = 0;
        $mock = $this->createMock(CommandRunner::class);
        $mock->expects($this->exactly(4))
            ->method('run')
            ->willReturnCallback(function ($cmd) use (&$calls) {
                $calls++;
                $sub = $this->subcommand($cmd);
                if ($sub === 'key list') {
                    if ($calls === 1) { return ['exitCode' => 0, 'stdout' => '[]', 'stderr' => '']; } // identify: не дубликат
                    if ($calls === 2) { return ['exitCode' => 0, 'stdout' => '[{"id":"old","current":true}]', 'stderr' => '']; } // before
                    return ['exitCode' => 0, 'stdout' => '[{"id":"old","current":true},{"id":"new","current":true}]', 'stderr' => '']; // after
                }
                // key add
                return ['exitCode' => 0, 'stdout' => 'saved new key', 'stderr' => ''];
            });

        $result = $this->service($mock)->addKey($this->repo, 'brand-new');

        $this->assertTrue($result['ok']);
        $this->assertSame('new', $result['key_id']);
    }

    /** removeKeyByPassword: отказ current_key ДО вызова `key remove`. */
    public function testRemoveKeyByPasswordRefusesWorkingKey(): void
    {
        $repo = array_merge($this->repo, ['password' => 'repo-pass']);

        $mock = $this->createMock(CommandRunner::class);
        // Только идентификация (листинг с паролем реквизитов, мемоизируется),
        // но НЕ `key remove`: рабочий ключ — отказ до вызова.
        $mock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (array $cmd) {
                $this->assertStringNotContainsString('remove', implode(' ', $cmd), 'key remove must not be called');
                return ['exitCode' => 0, 'stdout' => '[{"id":"worker","current":true}]', 'stderr' => ''];
            });

        $result = $this->service($mock)->removeKeyByPassword($repo, 'repo-pass');

        $this->assertFalse($result['ok']);
        $this->assertSame('current_key', $result['error_code']);
    }

    /** changePassword: RESTIC_PASSWORD = oldPassword; отказы same/taken. */
    public function testChangePasswordUsesOldPasswordAndRefusals(): void
    {
        $repo = array_merge($this->repo, ['password' => 'repo-pass']);

        // same_password — без обращений к restic.
        $noRun = $this->createMock(CommandRunner::class);
        $noRun->expects($this->never())->method('run');
        $same = $this->service($noRun)->changePassword($repo, 'pw', 'pw');
        $this->assertSame('same_password', $same['error_code']);

        // password_taken: новый пароль уже соответствует ключу.
        $taken = $this->createMock(CommandRunner::class);
        $taken->expects($this->once())
            ->method('run')
            ->willReturn(['exitCode' => 0, 'stdout' => '[{"id":"k","current":true}]', 'stderr' => '']);
        $result = $this->service($taken)->changePassword($repo, 'old', 'taken');
        $this->assertSame('password_taken', $result['error_code']);

        // Успешная смена: key passwd запускается с RESTIC_PASSWORD = old.
        $capturedEnv = null;
        $capturedStdin = null;
        $calls = 0;
        $ok = $this->createMock(CommandRunner::class);
        $ok->expects($this->exactly(5))
            ->method('run')
            ->willReturnCallback(function ($cmd, $env = [], $stdin = null) use (&$capturedEnv, &$capturedStdin, &$calls) {
                $calls++;
                $sub = $this->subcommand($cmd);
                if ($sub === 'key passwd') {
                    $capturedEnv = $env;
                    $capturedStdin = $stdin;
                    return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
                }
                // identify(new)→нет; identify(old)→есть; before; after
                if ($calls === 1) { return ['exitCode' => 0, 'stdout' => '[]', 'stderr' => '']; }
                if ($calls === 2) { return ['exitCode' => 0, 'stdout' => '[{"id":"oldkey","current":true}]', 'stderr' => '']; }
                if ($calls === 3) { return ['exitCode' => 0, 'stdout' => '[{"id":"oldkey","current":true}]', 'stderr' => '']; }
                return ['exitCode' => 0, 'stdout' => '[{"id":"newkey","current":true}]', 'stderr' => ''];
            });

        $success = $this->service($ok)->changePassword($repo, 'old-secret', 'new-secret');

        $this->assertTrue($success['ok']);
        $this->assertSame('newkey', $success['key_id']);
        $this->assertSame('old-secret', $capturedEnv['RESTIC_PASSWORD'], 'key passwd must run with the identified key password');
        $this->assertSame("new-secret\nnew-secret\n", $capturedStdin);
    }
}
