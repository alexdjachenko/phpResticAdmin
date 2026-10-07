<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

/**
 * Единственный источник правды о схеме метки задачи tsp.
 *
 * Метка несёт всё, что нужно интерфейсу, чтобы не заводить вторую очередь:
 *
 *   <username>#<op><repoId><rand16>   # операция по репозиторию
 *   <username>#<op><rand16>           # глобальная операция (без repoId)
 *
 * - op       — код операции из OPS (только строчные буквы);
 * - repoId   — id репозитория; длина не проверяется (в repositories.yaml id
 *              правят руками, hex-формат не гарантирован);
 * - rand16   — 16 hex-символов, уникальность задачи.
 *
 * Разбор идёт по словарю OPS от длинных к коротким, rand16 — последние 16
 * символов. Так разбор не зависит от длины repoId и не «съедает» операции,
 * начинающиеся с hex-буквы (check, forget) или с префикса другой (snapshots
 * / snapstats).
 */
final class TaskLabel
{
    /**
     * Коды операций, допустимые в метке (label op).
     *
     * @var array<int, string>
     */
    public const OPS = [
        'backup',
        'snapshots',
        'snapstats',
        'copysnap',
        'repair',
        'check',
        'prune',
        'unlock',
        'forget',
        'stats',
        'init',
        'run',
    ];

    /**
     * Глобальные операции (без repoId).
     *
     * @var array<int, string>
     */
    private const GLOBAL_OPS = ['run'];

    /**
     * Соответствие строки операции уровня сервиса коду в метке.
     *
     * @var array<string, string>
     */
    private const OPERATION_MAP = [
        'backup' => 'backup',
        'snapshots' => 'snapshots',
        'check' => 'check',
        'prune' => 'prune',
        'unlock' => 'unlock',
        'forget' => 'forget',
        'stats' => 'stats',
        'repair index' => 'repair',
        'init' => 'init',
        'copy' => 'copysnap',
        'snapstats' => 'snapstats',
    ];

    /**
     * Строит метку задачи.
     */
    public static function build(string $username, string $op, ?string $repoId = null): string
    {
        if (!in_array($op, self::OPS, true)) {
            throw new \InvalidArgumentException('Unknown task op: ' . $op);
        }

        if (self::isGlobal($op)) {
            if ($repoId !== null) {
                throw new \InvalidArgumentException('Global task op must not have a repoId: ' . $op);
            }
            $repoId = '';
        } elseif ($repoId === null || $repoId === '') {
            throw new \InvalidArgumentException('Repo task op requires a repoId: ' . $op);
        }

        return $username . '#' . $op . (string) $repoId . bin2hex(random_bytes(8));
    }

    /**
     * Переводит строку операции уровня сервиса в код метки.
     */
    public static function opForOperation(string $operation): string
    {
        if (!isset(self::OPERATION_MAP[$operation])) {
            throw new \InvalidArgumentException('Unknown maintenance operation: ' . $operation);
        }

        return self::OPERATION_MAP[$operation];
    }

    /**
     * Разбирает метку.
     *
     * @return array{username: string, op: string, repoId: ?string, rand: string}|null
     */
    public static function parse(string $label): ?array
    {
        $hashPos = strpos($label, '#');
        if ($hashPos === false || $hashPos === 0) {
            return null;
        }

        $username = substr($label, 0, $hashPos);
        if (preg_match('/^[A-Za-z0-9._-]+$/', $username) !== 1) {
            return null;
        }

        $rest = substr($label, $hashPos + 1);
        if (strlen($rest) < 16) {
            return null;
        }

        $rand = substr($rest, -16);
        if (preg_match('/^[0-9a-f]{16}$/', $rand) !== 1) {
            return null;
        }

        $middle = substr($rest, 0, -16);

        foreach (self::opsByLengthDesc() as $op) {
            if (!str_starts_with($middle, $op)) {
                continue;
            }

            $repoId = substr($middle, strlen($op));

            if (self::isGlobal($op)) {
                if ($repoId !== '') {
                    return null;
                }
                $repoId = null;
            } elseif ($repoId === '' || preg_match('/^[A-Za-z0-9._-]+$/', $repoId) !== 1) {
                return null;
            }

            return [
                'username' => $username,
                'op' => $op,
                'repoId' => $repoId,
                'rand' => $rand,
            ];
        }

        return null;
    }

    /**
     * Проверка формы метки.
     */
    public static function isValid(string $label): bool
    {
        return self::parse($label) !== null;
    }

    /**
     * Извлекает метку из строки вывода `tsp -l`.
     *
     * Возвращается только токен, целиком проходящий isValid(), а не подстрока
     * внутри мусора: это защищает от «обрезания» метки старой hex-регуляркой.
     */
    public static function extractFromTspLine(string $line): ?string
    {
        if (preg_match_all('/[A-Za-z0-9._-]+#[A-Za-z0-9._-]+/', $line, $matches) === false) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function isGlobal(string $op): bool
    {
        return in_array($op, self::GLOBAL_OPS, true);
    }

    /**
     * @return array<int, string>
     */
    private static function opsByLengthDesc(): array
    {
        $ops = self::OPS;
        usort($ops, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });

        return $ops;
    }
}
