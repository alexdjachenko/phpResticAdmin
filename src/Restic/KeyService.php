<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

use App\Cache\CacheInterface;

/**
 * Управление ключами restic.
 *
 * В restic «ключ» — это пароль: сколько разных паролей, столько ключей.
 * Команда `key list`, запущенная с проверяемым паролем, помечает
 * соответствующий ключ как `current` — это и есть идентификация ключа по
 * паролю. `key passwd` меняет пароль того ключа, чей пароль подан, поэтому
 * смена произвольного ключа выполнима.
 *
 * Таблица «какой пароль в какую команду» (часть контракта):
 *   identifyKey / verifyKey  — введённый пользователем пароль;
 *   listKeys (таблица, бейдж) — пароль реквизитов репозитория;
 *   key remove <id>           — пароль реквизитов репозитория;
 *   key passwd                — пароль идентифицированного ключа;
 *   key add                   — пароль реквизитов репозитория.
 *
 * Мемоизация (область текущего запроса): кешируется ТОЛЬКО листинг по паролю
 * реквизитов репозитория — один ключ на repoId. Любая мутация сбрасывает его.
 * Проверка чужого пароля кеш не трогает (иначе отрицательный результат,
 * закешированный до `key add`/`key passwd`, возвращался бы как актуальный).
 */
class KeyService
{
    private CommandRunner $runner;
    private ?CacheInterface $requestCache;

    public function __construct(CommandRunner $runner, ?CacheInterface $requestCache = null)
    {
        $this->runner = $runner;
        $this->requestCache = $requestCache;
    }

    /**
     * Быстрая проверка, что пароль подходит к репозиторию (restic cat config).
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, error: string}
     */
    public function verifyKey(array $repository, string $password): array
    {
        $result = $this->runWithPassword($repository, $password, ['cat', 'config'], null, 10);

        return [
            'ok' => $result['exitCode'] === 0,
            'error' => $result['stderr'],
        ];
    }

    /**
     * Список ключей репозитория (листинг с паролем реквизитов, мемоизация на запрос).
     *
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    public function listKeys(array $repository): array
    {
        return $this->repoKeyList($repository);
    }

    /**
     * Идентифицирует ключ по паролю: id ключа, помеченного `current`.
     *
     * Для пароля реквизитов переиспользует мемоизированный листинг; для
     * произвольного пароля делает разовый запрос без кеширования.
     *
     * @param array<string, mixed> $repository
     * @return array{id: string, current: bool}|null
     */
    public function identifyKey(array $repository, string $password): ?array
    {
        $repoPassword = $repository['password'] ?? null;
        $keys = ($repoPassword !== null && $password === $repoPassword)
            ? $this->repoKeyList($repository)
            : $this->fetchKeyList($repository, $password);

        foreach ($keys as $key) {
            if (!empty($key['current']) && !empty($key['id'])) {
                return ['id' => (string) $key['id'], 'current' => true];
            }
        }

        return null;
    }

    /**
     * Id рабочего ключа приложения (того, чей пароль в реквизитах).
     *
     * null, если реквизитов нет ИЛИ пароль не подошёл ни к одному ключу.
     * Различить эти случаи обязан вызывающий по repo['password'].
     *
     * @param array<string, mixed> $repository
     */
    public function workingKeyId(array $repository): ?string
    {
        $password = $repository['password'] ?? null;
        if (!is_string($password) || $password === '') {
            return null;
        }

        foreach ($this->repoKeyList($repository) as $key) {
            if (!empty($key['current']) && !empty($key['id'])) {
                return (string) $key['id'];
            }
        }

        return null;
    }

    /**
     * Добавляет ключ (пароль).
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, key_id: ?string, error_code: ?string, error: string}
     */
    public function addKey(array $repository, string $password): array
    {
        $existing = $this->identifyKey($repository, $password);
        if ($existing !== null) {
            return ['ok' => false, 'key_id' => $existing['id'], 'error_code' => 'duplicate', 'error' => ''];
        }

        $before = $this->keyIds($repository);

        $result = $this->runner->run(
            ResticCommandBuilder::buildCommand(['key', 'add'], $repository),
            ResticCommandBuilder::buildEnv($repository),
            $password . "\n" . $password . "\n"
        );

        $this->forgetKeyList($repository);

        if ($result['exitCode'] !== 0) {
            return ['ok' => false, 'key_id' => null, 'error_code' => 'failed', 'error' => $result['stderr']];
        }

        $after = $this->keyIds($repository);

        return ['ok' => true, 'key_id' => $this->diffId($before, $after), 'error_code' => null, 'error' => ''];
    }

    /**
     * Меняет пароль ключа, идентифицированного старым паролем.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, key_id: ?string, error_code: ?string, error: string}
     */
    public function changePassword(array $repository, string $oldPassword, string $newPassword): array
    {
        if ($newPassword === $oldPassword) {
            return ['ok' => false, 'key_id' => null, 'error_code' => 'same_password', 'error' => ''];
        }

        if ($this->identifyKey($repository, $newPassword) !== null) {
            return ['ok' => false, 'key_id' => null, 'error_code' => 'password_taken', 'error' => ''];
        }

        if ($this->identifyKey($repository, $oldPassword) === null) {
            return ['ok' => false, 'key_id' => null, 'error_code' => 'old_password_invalid', 'error' => ''];
        }

        $before = $this->keyIds($repository);

        // key passwd запускается с паролем идентифицированного ключа — для restic
        // именно этот ключ становится «текущим» и будет заменён.
        $result = $this->runWithPassword(
            $repository,
            $oldPassword,
            ['key', 'passwd'],
            $newPassword . "\n" . $newPassword . "\n"
        );

        $this->forgetKeyList($repository);

        if ($result['exitCode'] !== 0) {
            return ['ok' => false, 'key_id' => null, 'error_code' => 'failed', 'error' => $result['stderr']];
        }

        $after = $this->keyIds($repository);

        return ['ok' => true, 'key_id' => $this->diffId($before, $after), 'error_code' => null, 'error' => ''];
    }

    /**
     * Удаляет ключ, идентифицированный по паролю.
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, error_code: ?string, error: string}
     */
    public function removeKeyByPassword(array $repository, string $password): array
    {
        $identified = $this->identifyKey($repository, $password);
        if ($identified === null) {
            return ['ok' => false, 'error_code' => 'not_found', 'error' => ''];
        }

        $working = $this->workingKeyId($repository);
        if ($working !== null && $working === $identified['id']) {
            return ['ok' => false, 'error_code' => 'current_key', 'error' => ''];
        }

        return $this->removeKey($repository, $identified['id']);
    }

    /**
     * Удаляет ключ по id (запуск с паролем реквизитов).
     *
     * @param array<string, mixed> $repository
     * @return array{ok: bool, error_code: ?string, error: string}
     */
    public function removeKey(array $repository, string $keyId): array
    {
        if ($keyId === '') {
            return ['ok' => false, 'error_code' => 'not_found', 'error' => ''];
        }

        $working = $this->workingKeyId($repository);
        if ($working !== null && $working === $keyId) {
            return ['ok' => false, 'error_code' => 'current_key', 'error' => ''];
        }

        $result = $this->runner->run(
            ResticCommandBuilder::buildCommand(['key', 'remove', $keyId], $repository),
            ResticCommandBuilder::buildEnv($repository)
        );

        $this->forgetKeyList($repository);

        if ($result['exitCode'] !== 0) {
            return ['ok' => false, 'error_code' => 'failed', 'error' => $result['stderr']];
        }

        return ['ok' => true, 'error_code' => null, 'error' => ''];
    }

    /**
     * Листинг по реквизитам репозитория с мемоизацией на текущий запрос.
     *
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    private function repoKeyList(array $repository): array
    {
        $cacheKey = 'keys.list.' . (string) ($repository['id'] ?? '');
        $cache = $this->requestCache();

        if ($cache !== null) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $keys = $this->fetchKeyList($repository, $repository['password'] ?? null);

        if ($cache !== null) {
            $cache->set($cacheKey, $keys);
        }

        return $keys;
    }

    /**
     * Разовый листинг без кеширования (для произвольного пароля и диффов).
     *
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    private function fetchKeyList(array $repository, ?string $password): array
    {
        if ($password === null) {
            $result = $this->runner->run(
                ResticCommandBuilder::buildCommand(['key', 'list', '--json'], $repository),
                ResticCommandBuilder::buildEnv($repository)
            );
        } else {
            $result = $this->runWithPassword($repository, $password, ['key', 'list', '--json']);
        }

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $decoded = json_decode($result['stdout'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<int, string>
     */
    private function keyIds(array $repository): array
    {
        $ids = [];
        foreach ($this->fetchKeyList($repository, $repository['password'] ?? null) as $key) {
            if (!empty($key['id'])) {
                $ids[] = (string) $key['id'];
            }
        }
        return $ids;
    }

    /**
     * @param array<int, string> $before
     * @param array<int, string> $after
     */
    private function diffId(array $before, array $after): ?string
    {
        $new = array_values(array_diff($after, $before));

        return $new[0] ?? null;
    }

    /**
     * Запускает restic с явным паролем (override реквизитов).
     *
     * @param array<string, mixed> $repository
     * @param array<int, string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runWithPassword(array $repository, string $password, array $args, ?string $stdin = null, int $timeout = 30): array
    {
        $effective = array_merge($repository, ['password' => $password]);

        return $this->runner->run(
            ResticCommandBuilder::buildCommand($args, $effective),
            ResticCommandBuilder::buildEnv($effective),
            $stdin,
            $timeout
        );
    }

    private function forgetKeyList(array $repository): void
    {
        $cache = $this->requestCache();
        if ($cache !== null) {
            $cache->remove('keys.list.' . (string) ($repository['id'] ?? ''));
        }
    }

    private function requestCache(): ?CacheInterface
    {
        if ($this->requestCache === null) {
            $this->requestCache = \App\Core\App::cache()->request();
        }

        return $this->requestCache;
    }
}
