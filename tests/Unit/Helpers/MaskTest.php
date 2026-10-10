<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Helpers;

use App\Helpers\Mask;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест маскирования учётных данных в логах (App\Helpers\Mask).
 *
 * Цель: убедиться, что значения секретных полей (password, секреты, токены)
 *       заменяются на «***» по имени ключа, рекурсивно, а обычные данные
 *       остаются нетронутыми (иначе подробные debug-логи обесцениваются).
 *
 * Сценарий:
 *   1. password и _csrf_token → «***», обычное поле (username) сохраняется.
 *   2. Вложенные массивы: секрет внутри env маскируется, соседнее поле — нет.
 *   3. Варианты имён (регистр/разделители), секреты и токены → «***».
 *   4. Обычные поля (значения, числа) не меняются.
 *   5. Маскирование идёт по имени поля, а не по содержимому значения.
 *
 * Критерий успеха: все assert проходят.
 */
class MaskTest extends TestCase
{
    /** Тело login-формы: пароль и CSRF-токен маскируются, логин остаётся. */
    public function testMasksPasswordAndCsrfTokenKeepsUsername(): void
    {
        $masked = Mask::data([
            'username' => 'alice',
            'password' => 's3cr3t!',
            '_csrf_token' => 'abc123def456',
        ]);

        $this->assertSame('alice', $masked['username']);
        $this->assertSame('***', $masked['password']);
        $this->assertSame('***', $masked['_csrf_token']);
    }

    /** Секрет во вложенном массиве маскируется, соседнее поле — нет. */
    public function testMasksNestedCredentialFields(): void
    {
        $masked = Mask::data([
            'env' => [
                'AWS_ACCESS_KEY_ID' => 'AKIA123',
                'AWS_SECRET_ACCESS_KEY' => 'sekret',
                'AWS_ENDPOINT' => 'https://s3.example.com',
            ],
        ]);

        $this->assertSame('***', $masked['env']['AWS_ACCESS_KEY_ID']);
        $this->assertSame('***', $masked['env']['AWS_SECRET_ACCESS_KEY']);
        $this->assertSame('https://s3.example.com', $masked['env']['AWS_ENDPOINT']);
    }

    /** Варианты имён (регистр и разделители) распознаются одинаково. */
    public function testMasksCredentialKeyVariantsCaseInsensitively(): void
    {
        $masked = Mask::data([
            'old_password' => 'a',
            'New-Password' => 'b',
            'api_tokens' => ['t1', 't2'],
            's3_secret' => 'c',
            'session_token' => 'd',
            'password_var' => 'PHPRESTICADMIN_ADMIN_PASSWORD_HASH',
        ]);

        foreach (['old_password', 'New-Password', 'api_tokens', 's3_secret', 'session_token', 'password_var'] as $key) {
            $this->assertSame('***', $masked[$key], $key . ' must be masked');
        }
    }

    /** Обычные поля не меняются. */
    public function testKeepsNonSensitiveValuesUntouched(): void
    {
        $input = [
            'username' => 'bob',
            'repo_id' => 'a1b2c3d4',
            'count' => 3,
            'path' => '/sources',
            'enabled' => true,
        ];

        $this->assertSame($input, Mask::data($input));
    }

    /** Маскирование по имени поля, а не по содержимому значения. */
    public function testMasksByKeyNameNotByValueContent(): void
    {
        $masked = Mask::data(['note' => 'the password is hunter2']);

        $this->assertSame('the password is hunter2', $masked['note']);
    }

    /** isSensitive распознаёт секретные имена и не трогает обычные. */
    public function testIsSensitive(): void
    {
        $this->assertTrue(Mask::isSensitive('password'));
        $this->assertTrue(Mask::isSensitive('_csrf_token'));
        $this->assertTrue(Mask::isSensitive('AWS_SECRET_ACCESS_KEY'));

        $this->assertFalse(Mask::isSensitive('username'));
        $this->assertFalse(Mask::isSensitive('repo_id'));
    }
}
