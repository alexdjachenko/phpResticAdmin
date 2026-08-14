<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Restic;

use App\Restic\ResticCommandBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест ResticCommandBuilder (сборка команд и окружения restic).
 *
 * Цель: проверить порядок глобальных флагов, передачу пароля в env,
 *       сборку S3-URL и вычищение AWS_ENDPOINT из env.
 *
 * Критерий успеха: все assert проходят.
 */
class ResticCommandBuilderTest extends TestCase
{
    /** Порядок: --insecure-no-password и --repo до подкоманды. */
    public function testBuildCommandOrderWithoutPassword(): void
    {
        $cmd = ResticCommandBuilder::buildCommand(['snapshots', '--json'], [
            'type' => 'local',
            'local_path' => '/backups/repo',
            'password' => null,
        ]);

        $this->assertSame('restic', $cmd[0]);
        $this->assertSame('--insecure-no-password', $cmd[1]);
        $this->assertSame('--repo', $cmd[2]);
        $this->assertSame('/backups/repo', $cmd[3]);
        $this->assertSame('snapshots', $cmd[4]);
    }

    /** С паролем --insecure-no-password не добавляется. */
    public function testBuildCommandWithPasswordNoInsecureFlag(): void
    {
        $cmd = ResticCommandBuilder::buildCommand(['init'], [
            'type' => 'local',
            'local_path' => '/backups/repo',
            'password' => 'secret',
        ]);

        $this->assertNotContains('--insecure-no-password', $cmd);
        $this->assertSame('--repo', $cmd[1]);
    }

    /** S3-репозиторий → --repo s3:... */
    public function testBuildCommandS3Repo(): void
    {
        $cmd = ResticCommandBuilder::buildCommand(['init'], [
            'type' => 's3',
            's3_bucket' => 'my-bucket',
            'password' => null,
        ]);

        $repoPos = array_search('--repo', $cmd, true);
        $this->assertIsInt($repoPos);
        $this->assertSame('s3:s3.amazonaws.com/my-bucket', $cmd[$repoPos + 1]);
    }

    /** AWS_ENDPOINT вычищается из env, credentials остаются. */
    public function testBuildEnvStripsAwsEndpointKeepsCredentials(): void
    {
        $env = ResticCommandBuilder::buildEnv([
            'password' => null,
            'env' => [
                'AWS_ENDPOINT' => 'https://s3.example.com',
                'AWS_ACCESS_KEY_ID' => 'AKIA123',
                'AWS_SECRET_ACCESS_KEY' => 'secret',
            ],
        ]);

        $this->assertArrayNotHasKey('AWS_ENDPOINT', $env);
        $this->assertSame('AKIA123', $env['AWS_ACCESS_KEY_ID']);
        $this->assertSame('secret', $env['AWS_SECRET_ACCESS_KEY']);
    }

    /** Пароль → RESTIC_PASSWORD в env. */
    public function testBuildEnvSetsResticPassword(): void
    {
        $env = ResticCommandBuilder::buildEnv(['password' => 'secret', 'env' => []]);

        $this->assertSame('secret', $env['RESTIC_PASSWORD']);
    }
}
