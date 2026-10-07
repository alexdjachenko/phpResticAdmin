<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Process\TaskLabel;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест схемы метки задачи (чистый класс, без I/O).
 *
 * Цель: проверить построение и разбор метки для всех операций словаря, включая
 *       операции, начинающиеся с hex-буквы (check, forget), префиксные
 *       (snapshots / snapstats) и глобальные (run).
 *
 * Сценарий:
 *   - build → parse даёт те же username/op/repoId.
 *   - repoId не обязан быть hex и может быть коротким.
 *   - старый формат user#hex, мусор, пустая метка и метка с '#' в середине
 *     отвергаются.
 *   - extractFromTspLine находит метку в строке вывода tsp и не «обрезает» её.
 *
 * Критерий успеха: все assert проходят.
 */
class TaskLabelTest extends TestCase
{
    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function opProvider(): array
    {
        return [
            'backup' => ['backup', 'r1', 'backup'],
            'snapshots' => ['snapshots', 'r1', 'snapshots'],
            'check (hex-буква)' => ['check', 'r1', 'check'],
            'forget (hex-буква)' => ['forget', 'r1', 'forget'],
            'prune' => ['prune', 'r1', 'prune'],
            'unlock' => ['unlock', 'r1', 'unlock'],
            'repair (from repair index)' => ['repair', 'r1', 'repair'],
            'init' => ['init', 'r1', 'init'],
            'stats (repo)' => ['stats', 'r1', 'stats'],
            'snapstats (snapshot)' => ['snapstats', 'r1', 'snapstats'],
            'copysnap (copy)' => ['copysnap', 'r1', 'copysnap'],
            'run (global)' => ['run', null, 'run'],
        ];
    }

    /**
     * build → parse возвращает исходные данные для каждой операции.
     *
     * @dataProvider opProvider
     */
    public function testBuildThenParse(string $op, ?string $repoId, string $expectedOp): void
    {
        $label = TaskLabel::build('alice', $op, $repoId);
        $parsed = TaskLabel::parse($label);

        $this->assertNotNull($parsed);
        $this->assertSame('alice', $parsed['username']);
        $this->assertSame($expectedOp, $parsed['op']);
        $this->assertSame($repoId, $parsed['repoId']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $parsed['rand']);
    }

    /** snapshots не путается со snapstats, copysnap — с check. */
    public function testPrefixOpsAreDistinguished(): void
    {
        $snapshots = TaskLabel::parse('alice#snapshotsr10123456789abcdef');
        $this->assertNotNull($snapshots);
        $this->assertSame('snapshots', $snapshots['op']);
        $this->assertSame('r1', $snapshots['repoId']);

        $snapstats = TaskLabel::parse('alice#snapstatsr10123456789abcdef');
        $this->assertNotNull($snapstats);
        $this->assertSame('snapstats', $snapstats['op']);
        $this->assertSame('r1', $snapstats['repoId']);

        $copysnap = TaskLabel::parse('alice#copysnapr10123456789abcdef');
        $this->assertNotNull($copysnap);
        $this->assertSame('copysnap', $copysnap['op']);
    }

    /** repoId не обязан быть hex и может быть коротким. */
    public function testNonHexRepoIdIsAccepted(): void
    {
        $label = TaskLabel::build('alice', 'snapshots', 'a1b2c3d4e5f6g7h8');
        $parsed = TaskLabel::parse($label);

        $this->assertNotNull($parsed);
        $this->assertSame('a1b2c3d4e5f6g7h8', $parsed['repoId']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLabelProvider(): array
    {
        return [
            'old format' => ['alice#3f2a9c1b'],
            'garbage' => ['not-a-label'],
            'empty' => [''],
            'hash in middle' => ['alice#check#0123456789abcdef'],
            'unknown op' => ['alice#frobnicate0123456789abcdef'],
            'no username' => ['#check0123456789abcdef'],
            'too short' => ['alice#checkabc'],
            'non-hex rand' => ['alice#checkr1zzzzzzzzzzzzzzzz'],
            'repo op without repoId' => ['alice#check0123456789abcdef'],
        ];
    }

    /**
     * @dataProvider invalidLabelProvider
     */
    public function testInvalidLabels(string $label): void
    {
        $this->assertFalse(TaskLabel::isValid($label));
        $this->assertNull(TaskLabel::parse($label));
    }

    /** extractFromTspLine находит метку и не обрезает её у операций на hex-букву. */
    public function testExtractFromTspLine(): void
    {
        $line = '7   finished   /usr/bin/restic check   alice#checkr10123456789abcdef   0';
        $this->assertSame('alice#checkr10123456789abcdef', TaskLabel::extractFromTspLine($line));

        $line = '9   running   restic snapshots   bob#snapshotsr20123456789abcdef   0   1';
        $this->assertSame('bob#snapshotsr20123456789abcdef', TaskLabel::extractFromTspLine($line));

        $this->assertNull(TaskLabel::extractFromTspLine('7   finished   echo hello   0'));
    }

    /** Глобальная операция не принимает repoId. */
    public function testBuildRunRejectsRepoId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaskLabel::build('alice', 'run', 'r1');
    }

    /** Неизвестная операция при построении — ошибка программиста. */
    public function testBuildUnknownOpThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaskLabel::build('alice', 'frobnicate', 'r1');
    }
}
