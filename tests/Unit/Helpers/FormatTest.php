/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Helpers;

use App\Helpers\Format;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Format (форматирование байтов, дат, timeAgo, truncate).
 *
 * Цель: проверить вспомогательные функции форматирования.
 *
 * Сценарий:
 *   - bytes(): 0 → "0 B", 1024 → "1.00 KiB", 1536 → "1.50 KiB", GiB и т.д.
 *   - date(): ISO-8601 → "Y-m-d H:i:s", невалидная строка → возвращается как есть.
 *   - timeAgo(): секунды/минуты/часы/дни назад.
 *   - truncate(): короткая строка без изменений, длинная обрезается с "...".
 *
 * Критерий успеха: все assertSame/assertStringContainsString проходят.
 */
class FormatTest extends TestCase
{
    /** Проверяет форматирование байтов: B, KiB, MiB, GiB. */
    public function testBytesFormatsCorrectly(): void
    {
        $this->assertSame('0 B', Format::bytes(0));
        $this->assertSame('1 B', Format::bytes(1));
        $this->assertSame('1.00 KiB', Format::bytes(1024));
        $this->assertSame('1.50 KiB', Format::bytes(1536));
        $this->assertSame('1.00 MiB', Format::bytes(1048576));
        $this->assertSame('1.00 GiB', Format::bytes(1073741824));
    }

    /** Форматирование ISO-8601 даты в "Y-m-d H:i:s". */
    public function testDateFormatsIso8601(): void
    {
        $this->assertSame('2025-01-15 10:30:00', Format::date('2025-01-15T10:30:00+00:00'));
    }

    /** Невалидная дата возвращается как есть (без изменений). */
    public function testDateReturnsOriginalOnInvalid(): void
    {
        $bad = 'not-a-date';
        $this->assertSame($bad, Format::date($bad));
    }

    /** timeAgo: проверяет строки для разных интервалов. */
    public function testTimeAgoReturnsCorrectString(): void
    {
        // 30 секунд назад
        $now = date('c', time() - 30);
        $this->assertStringContainsString('sec ago', Format::timeAgo($now));

        // 2 минуты назад
        $minAgo = date('c', time() - 120);
        $this->assertStringContainsString('min ago', Format::timeAgo($minAgo));

        // 2 часа назад
        $hoursAgo = date('c', time() - 7200);
        $this->assertStringContainsString('hours ago', Format::timeAgo($hoursAgo));

        // 2 дня назад
        $daysAgo = date('c', time() - 172800);
        $this->assertStringContainsString('days ago', Format::timeAgo($daysAgo));
    }

    /** truncate: короткая строка без изменений, длинная — с "...". */
    public function testTruncateShortensLongStrings(): void
    {
        // Строка короче лимита — без изменений
        $this->assertSame('abc', Format::truncate('abc', 10));
        // Строка длиннее лимита — обрезается с "..."
        $this->assertSame('abcdef...', Format::truncate('abcdefghij', 9));
        // Граничный случай: лимит больше длины — без изменений
        $this->assertSame('ab', Format::truncate('ab', 5));
    }
}
