<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Process\TaskLabel;
use App\Process\TaskPostProcessor;
use App\Process\TspTaskManager;
use App\Restic\ResticTaskService;

/**
 * Резолвер контента: три сознательно разделённые операции над целевым объектом.
 *
 *   1. read(target)      — только чтение состояния (ничего не пишет и не запускает);
 *   2. schedule(target)  — поставить tsp-задачу и записать обещание (кнопка UI);
 *   3. reconcile(target) — довести до конца: если задача завершена, разобрать её
 *                          вывод и записать контент. Идемпотентно, задач НЕ ставит.
 *
 * Разделение read/schedule отвечает требованию «запросить данные и
 * инициировать запрос с обещанием — разные операции»: GET-страница вызывает
 * read() + reconcile() (постобработку готового вывода) и НИКОГДА не schedule().
 */
class ContentResolver
{
    private ContentCacheStorage $storage;
    private TspTaskManager $tasks;
    private ResticTaskService $resticTasks;
    private TaskPostProcessor $processor;
    private RepositoryStorage $repos;

    public function __construct(
        ContentCacheStorage $storage,
        TspTaskManager $tasks,
        ResticTaskService $resticTasks,
        TaskPostProcessor $processor,
        RepositoryStorage $repos
    ) {
        $this->storage = $storage;
        $this->tasks = $tasks;
        $this->resticTasks = $resticTasks;
        $this->processor = $processor;
        $this->repos = $repos;
    }

    /**
     * Состояние целевого объекта (чистое чтение).
     *
     * @return array{
     *   state: string,
     *   value?: mixed,
     *   promise?: ?string,
     *   error?: ?string,
     *   cached_at?: ?int,
     *   meta?: ?array,
     *   task_state?: ?string,
     *   position?: ?int
     * }
     */
    public function read(ContentTarget $target): array
    {
        $entry = $this->storage->read($target);

        if ($entry === null) {
            return ['state' => 'absent'];
        }

        $taskState = null;
        $position = null;

        if ($entry->promise !== null) {
            $job = $this->tasks->findByLabel($entry->promise);
            if ($job !== null) {
                $taskState = (string) ($job['state'] ?? 'unknown');
                if ($taskState === 'queued') {
                    $position = $this->tasks->queuePosition($entry->promise);
                }
            }
        }

        // Состояние вычисляется из записи и статуса задачи. Обещание без живой
        // задачи (пропала из очереди после tsp -C, skipped/unknown) считаем
        // отсутствием: страница предложит запросить данные заново.
        if ($entry->value !== null && !$entry->stale) {
            $state = 'ready';
        } elseif ($entry->error !== null) {
            $state = 'failed';
        } elseif ($entry->promise !== null && in_array($taskState, ['queued', 'running'], true)) {
            $state = 'waiting';
        } elseif ($entry->promise !== null && $taskState === 'finished') {
            $state = 'ready_to_process';
        } else {
            $state = 'absent';
        }

        return [
            'state' => $state,
            'value' => $entry->value,
            'promise' => $entry->promise,
            'error' => $entry->error,
            'cached_at' => $entry->cachedAt,
            'meta' => $entry->meta,
            'task_state' => $taskState,
            'position' => $position,
        ];
    }

    /**
     * Ставит фоновую задачу для целевого объекта и записывает обещание.
     *
     * Если активная задача по этому объекту уже есть (в очереди/выполняется) —
     * вторую не ставим, возвращаем её метку: так повторное нажатие кнопки не
     * размножает задачи.
     *
     * @param array<string, mixed> $repo репозиторий (нужен для сборки команды)
     * @return array{label: string, id: int}
     */
    public function schedule(ContentTarget $target, array $repo): array
    {
        $active = $this->activeFor($target);
        if ($active !== null) {
            return ['label' => (string) $active['label'], 'id' => (int) $active['id']];
        }

        if ($target->kind === ContentTarget::KIND_SNAPSHOT_STATS) {
            $started = $this->resticTasks->startSnapshotStats($repo, $target->objectId);
        } else {
            $started = $this->resticTasks->startListSnapshots($repo);
        }

        if (($started['id'] ?? -1) >= 0) {
            $this->storage->promise($target, (string) $started['label']);
        } else {
            $this->storage->fail($target, __('snap.load_error'));
        }

        return $started;
    }

    /**
     * Довести обработку до конца, если вывод задачи уже готов. Идемпотентно.
     *
     * Только эта операция инициирует постобработку — задач не ставит.
     */
    public function reconcile(ContentTarget $target): bool
    {
        $entry = $this->storage->read($target);
        if ($entry === null) {
            return false;
        }

        if ($entry->value !== null && !$entry->stale) {
            return false;
        }

        if ($entry->error !== null) {
            return false;
        }

        $label = $entry->promise;
        if ($label === null) {
            return false;
        }

        $job = $this->tasks->findByLabel($label);
        if ($job === null || (string) ($job['state'] ?? '') !== 'finished') {
            return false;
        }

        $parsed = TaskLabel::parse($label);
        if ($parsed === null) {
            return false;
        }

        $handled = $this->processor->process(
            $parsed['op'],
            $parsed['username'],
            $label,
            true,
            $target,
            $entry->duration()
        );

        return $handled === true;
    }

    /**
     * Довести до конца по метке задачи (для дашборда: «по завершённой задаче
     * найти обещание и обработать вывод»).
     */
    public function reconcileByLabel(string $label): bool
    {
        $parsed = TaskLabel::parse($label);
        if ($parsed === null) {
            return false;
        }

        $target = null;

        if ($parsed['op'] === 'snapstats') {
            $objectId = $parsed['objectId'] ?? null;
            if ($objectId !== null && $objectId !== '') {
                $target = ContentTargets::forStats((string) ($parsed['repoId'] ?? ''), $objectId);
            }
        } elseif ($parsed['op'] === 'snapshots' && ($parsed['repoId'] ?? null) !== null) {
            $repoId = (string) $parsed['repoId'];
            $repo = $this->findRepo($parsed['username'], $repoId);
            $target = $repo !== null
                ? ContentTargets::forSnapshots($repo)
                : ContentTargets::fromLabel($label);
        }

        if ($target === null) {
            return false;
        }

        return $this->reconcile($target);
    }

    /**
     * Активная задача по целевому объекту (в очереди/выполняется), либо null.
     *
     * @return array{id: int, state: string, label: string, objectId: ?string}|null
     */
    private function activeFor(ContentTarget $target): ?array
    {
        foreach ($this->tasks->listActiveFor($target->op(), $target->repoId) as $job) {
            if (!in_array($job['state'], ['queued', 'running'], true)) {
                continue;
            }

            if ($target->kind === ContentTarget::KIND_SNAPSHOT_STATS
                && ($job['objectId'] ?? null) !== $target->objectId) {
                continue;
            }

            return $job;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRepo(string $username, string $repoId): ?array
    {
        foreach ($this->repos->loadAll($username) as $repo) {
            if (($repo['id'] ?? '') === $repoId) {
                return $repo;
            }
        }

        return null;
    }
}
