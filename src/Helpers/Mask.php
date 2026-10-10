<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Helpers;

/**
 * Маскирование учётных данных в логах.
 *
 * Диагностические логи (debug >= 1) могут печатать тела запросов целиком.
 * Чтобы сохранить подробность, но не утекать секретами, значения полей, чьи
 * имена похожи на учётные данные (password / secret / token / ...), перед
 * записью в лог заменяются на «***». Уровень вложенности не ограничен.
 *
 * Маскирование идёт ТОЛЬКО по имени поля, а не по содержимому значения: так
 * «the password is hunter2» в обычном поле останется как есть, а value под
 * ключом password — нет.
 */
final class Mask
{
    /**
     * Подстроки в нормализованном имени поля, при которых значение считается
     * учётным и подлежит маскированию. Имя нормализуется: нижний регистр и
     * удаление разделителей (_, -, .), поэтому AWS_SECRET_ACCESS_KEY,
     * aws-secret-access-key и awsSecretAccessKey срабатывают одинаково.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_PARTS = [
        'password',
        'passphrase',
        'secret',
        'token',
        'apikey',
        'accesskey',
        'privatekey',
        'credential',
    ];

    private const REDACTED = '***';

    /**
     * Возвращает копию массива, в которой значения секретных полей заменены
     * на «***». Секретное имя маскирует значение ЦЕЛИКОМ (даже если это массив,
     * например api_tokens); остальные вложенные массивы обрабатываются рекурсивно.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function data(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            // Секретное имя: маскируем значение целиком, даже если это список
            // (api_tokens) — иначе элементы утекли бы в лог.
            if (is_string($key) && self::isSensitive($key)) {
                $masked[$key] = self::REDACTED;
                continue;
            }

            $masked[$key] = is_array($value) ? self::data($value) : $value;
        }

        return $masked;
    }

    /**
     * Похоже ли имя ключа на учётные данные (регистр и разделители не важны).
     */
    public static function isSensitive(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';

        foreach (self::SENSITIVE_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
