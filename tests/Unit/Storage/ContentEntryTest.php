<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Storage\ContentEntry;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест состояния записи кеша (ContentEntry::state()).
 *
 * Цель: проверить вычисление всех пяти состояний из комбинации полей записи
 *       (value/promise/error) и состояния tsp-задачи.
 *
 * Сценарий: собираем записи для каждой ветви таблицы состояний.
 *
 * Критерий успеха: state() возвращает ready|waiting|ready_to_process|failed|absent.
 */
class ContentEntryTest extends TestCase
{
    private function entry(mixed $value, ?string $promise, ?string $error, bool $stale = false): ContentEntry
    {
        return new ContentEntry($value, $promise, $error, time(), null, $stale);
    }

    /** Есть свежее значение → ready. */
    public function testReadyWhenValuePresent(): void
    {
        $entry = $this->entry([['id' => 'a']], null, null);

        $this->assertSame('ready', $entry->state());
    }

    /** Обещание + задача в очереди/выполняется → waiting. */
    public function testWaitingWhileTaskActive(): void
    {
        $entry = $this->entry(null, 'alice#snapshotsr10123456789abcdef', null);

        $this->assertSame('waiting', $entry->state('queued'));
        $this->assertSame('waiting', $entry->state('running'));
    }

    /** Обещание + задача завершена → ready_to_process. */
    public function testReadyToProcessWhenTaskFinished(): void
    {
        $entry = $this->entry(null, 'alice#snapshotsr10123456789abcdef', null);

        $this->assertSame('ready_to_process', $entry->state('finished'));
    }

    /** Ошибка (липкая) → failed, даже если значение отсутствует. */
    public function testFailedWhenErrorPresent(): void
    {
        $entry = $this->entry(null, 'label', 'boom');

        $this->assertSame('failed', $entry->state('finished'));
    }

    /** Пустая запись → absent. */
    public function testAbsentWhenEmpty(): void
    {
        $entry = $this->entry(null, null, null);

        $this->assertSame('absent', $entry->state());
    }

    /** Просроченное значение без обещания/ошибки → absent (нужен повторный запрос). */
    public function testStaleValueWithoutPromiseIsAbsent(): void
    {
        $entry = $this->entry([['id' => 'a']], null, null, true);

        $this->assertSame('absent', $entry->state());
    }
}
