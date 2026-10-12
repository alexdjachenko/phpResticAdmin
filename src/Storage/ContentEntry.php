<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

/**
 * Запись кеша производного контента: значение и «обещание».
 *
 * Контент и обещание живут в ОДНОЙ записи целевого объекта: либо есть готовое
 * значение, либо есть метка tsp-задачи, которая его наполнит, либо липкая
 * ошибка. Состояние вычисляется из комбинации полей — отдельный бит «вывод
 * обработан» не нужен.
 */
final class ContentEntry
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?string $promise,
        public readonly ?string $error,
        public readonly int $cachedAt,
        public readonly ?array $meta,
        public readonly bool $stale,
    ) {
    }

    /**
     * Состояние записи.
     *
     * `$taskState` — состояние tsp-задачи из обещания (queued/running/finished/
     * ...) или null, если задача неизвестна. Нужно, чтобы отличить «ждём
     * задачу» (waiting) от «задача отработала — можно обрабатывать вывод»
     * (ready_to_process).
     *
     * - ready            — есть свежее значение;
     * - failed           — зафиксирована ошибка (липкая: повтор только по кнопке);
     * - waiting          — обещание есть, задача в очереди/выполняется;
     * - ready_to_process — обещание есть, задача завершена;
     * - absent           — данных нет, интерфейс решает, запрашивать ли.
     */
    public function state(?string $taskState = null): string
    {
        if ($this->value !== null && !$this->stale) {
            return 'ready';
        }

        if ($this->error !== null) {
            return 'failed';
        }

        if ($this->promise !== null) {
            if (in_array($taskState, ['queued', 'running'], true)) {
                return 'waiting';
            }
            if ($taskState === 'finished') {
                return 'ready_to_process';
            }

            return 'waiting';
        }

        return 'absent';
    }

    /**
     * Длительность вычисления (секунды) из метаданных записи.
     */
    public function duration(): ?int
    {
        if ($this->meta === null) {
            return null;
        }

        if (isset($this->meta['duration'])) {
            return (int) $this->meta['duration'];
        }

        if (isset($this->meta['started_at'])) {
            return max(0, time() - (int) $this->meta['started_at']);
        }

        return null;
    }
}
