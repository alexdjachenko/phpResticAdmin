<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

use App\Restic\SnapshotService;
use App\Storage\ContentCacheStorage;
use App\Storage\ContentTarget;

/**
 * Постобработка вывода завершённой tsp-задачи: реестр `op -> processor`.
 *
 * Постобработка — это разбор УЖЕ ГОТОВОГО вывода задачи и запись контента в
 * кеш. Она посильна в процессе генерации страницы (в отличие от самих процессов
 * tsp), поэтому выполняется inline, а не отдельной tsp-задачей.
 *
 * Идемпотентность обеспечивается хранилищем: успешная запись снимает обещание
 * (`promise = null`), поэтому повторный вызов уже не увидит `ready_to_process`.
 */
class TaskPostProcessor
{
    private TspTaskManager $tasks;
    private ContentCacheStorage $storage;

    public function __construct(TspTaskManager $tasks, ContentCacheStorage $storage)
    {
        $this->tasks = $tasks;
        $this->storage = $storage;
    }

    /**
     * Обрабатывает вывод задачи для целевого объекта.
     *
     * @return bool|null true — контент записан; false — обработчик есть, но вывод
     *         не разобран (зафиксирована ошибка); null — для этой op
     *         постобработка не требуется (кеш не тронут).
     */
    public function process(
        string $op,
        string $username,
        string $label,
        bool $privileged,
        ContentTarget $target,
        ?int $duration = null
    ): ?bool {
        $processor = $this->processorFor($op);
        if ($processor === null) {
            return null;
        }

        $result = $this->tasks->catResult($username, $label, $privileged);

        if ($result === null || $result['exitCode'] !== 0) {
            $this->storage->fail($target, $this->errorDetail($op, $result), $label);
            return false;
        }

        return $processor($result['output'], $target, $duration);
    }

    /**
     * Человекочитаемая причина ошибки: stderr задачи, затем её stdout.
     *
     * @param array{exitCode: int, output: string, stderr: string}|null $result
     */
    private function errorDetail(string $op, ?array $result): string
    {
        $fallback = $op === 'snapstats' ? __('snap.stats_failed') : __('snap.load_error');

        if (!is_array($result)) {
            return $fallback;
        }

        $detail = trim((string) ($result['stderr'] ?? ''));
        if ($detail === '') {
            $detail = trim((string) ($result['output'] ?? ''));
        }

        return $detail !== '' ? $detail : $fallback;
    }

    /**
     * @return callable(string, ContentTarget, ?int): bool|null
     */
    private function processorFor(string $op): ?callable
    {
        return match ($op) {
            'snapshots' => function (string $output, ContentTarget $target, ?int $duration): bool {
                $decoded = json_decode(trim($output), true);
                if (!is_array($decoded)) {
                    $this->storage->fail($target, __('snap.load_error'));
                    return false;
                }
                $this->storage->put($target, $decoded, ['duration' => $duration]);
                return true;
            },
            'snapstats' => function (string $output, ContentTarget $target, ?int $duration): bool {
                $stats = SnapshotService::parseStatsOutput($output);
                if ($stats === null) {
                    $this->storage->fail($target, __('snap.stats_failed'));
                    return false;
                }
                $this->storage->put($target, $stats, ['duration' => $duration]);
                return true;
            },
            default => null,
        };
    }
}
