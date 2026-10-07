<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Process\TspTaskManager;

/**
 * Автомат состояния страницы списка снепшотов.
 *
 * Без HTTP: по записи кеша и активным задачам tsp принимает решение, что
 * показать. Это защищает от «бесконечного перезапуска»: авто-старт задачи
 * возможен только когда в записи нет метки либо задача пропала из очереди.
 * Если зафиксирована ошибка — она показывается, повтор только по кнопке
 * (инвалидация записи снаружи).
 */
class SnapshotListState
{
    private SnapshotCacheStorage $cache;
    private TspTaskManager $tasks;

    public function __construct(SnapshotCacheStorage $cache, TspTaskManager $tasks)
    {
        $this->cache = $cache;
        $this->tasks = $tasks;
    }

    /**
     * @return array{
     *   state: string,
     *   snapshots?: array<int, array<string, mixed>>,
     *   computed_at?: int,
     *   duration?: ?int,
     *   task_label?: ?string,
     *   task_state?: ?string,
     *   position?: ?int,
     *   error?: string
     * }
     */
    public function resolve(array $repo, string $username, bool $privileged): array
    {
        $entry = $this->cache->listEntry($repo);

        // 1. Свежая запись со списком — отдаём как есть.
        if ($entry !== null && !$entry['stale'] && $entry['snapshots'] !== null) {
            return [
                'state' => 'list',
                'snapshots' => $entry['snapshots'],
                'computed_at' => $entry['computed_at'],
                'duration' => $entry['duration'],
                'task_label' => $entry['task_label'],
            ];
        }

        // 2. Зафиксированная ошибка «липкая»: показываем её без авто-старта.
        if ($entry !== null && $entry['error'] !== null) {
            return [
                'state' => 'error',
                'error' => $entry['error'],
                'task_label' => $entry['task_label'],
            ];
        }

        $label = $entry['task_label'] ?? null;

        // 3. Метки нет (или она невалидна) — нужен старт новой задачи.
        if ($label === null || !$this->tasks->isValidLabel($label)) {
            return ['state' => 'start'];
        }

        // 4. Задача пропала из очереди (например, после tsp -C) — старт новой.
        $job = $this->tasks->findByLabel($label);
        if ($job === null) {
            return ['state' => 'start'];
        }

        $taskState = (string) ($job['state'] ?? 'unknown');

        // 5. Задача ещё в очереди/выполняется — показываем прогресс.
        if (in_array($taskState, ['queued', 'running'], true)) {
            return [
                'state' => 'progress',
                'task_label' => $label,
                'task_state' => $taskState,
                'position' => $taskState === 'queued' ? $this->tasks->queuePosition($label) : null,
            ];
        }

        // 6. Задача завершена: разбираем результат.
        if ($taskState === 'finished') {
            $result = $this->tasks->catResult($username, $label, $privileged);

            if ($result !== null && $result['exitCode'] === 0) {
                $decoded = json_decode($result['output'], true);
                if (is_array($decoded)) {
                    $duration = $entry['started_at'] !== null ? max(0, time() - $entry['started_at']) : null;
                    $this->cache->setList($repo, $decoded, null, $duration);

                    return [
                        'state' => 'list',
                        'snapshots' => $decoded,
                        'computed_at' => time(),
                        'duration' => $duration,
                        'task_label' => null,
                    ];
                }
            }

            $this->cache->setListError($repo, __('snap.load_error'), $label);
            return ['state' => 'error', 'error' => __('snap.load_error'), 'task_label' => $label];
        }

        // 7. Неизвестное состояние — считаем задачу потерянной.
        return ['state' => 'start'];
    }
}
