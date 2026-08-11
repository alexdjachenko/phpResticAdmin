<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use App\Restic\CommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Комплексный end-to-end интеграционный тест снапшотов.
 *
 * Цель: проверить полный жизненный цикл снапшотов — от создания до удаления —
 *       с проверкой содержимого, размеров, изоляции данных между снапшотами,
 *       экспорта и обслуживания (forget + check).
 *
 * Сценарий (в порядке выполнения тестов):
 *   1. Создаются 3 снапшота с контролируемым содержимым:
 *      - backup-1: file_a.txt (100 байт), file_b.txt (200 байт)
 *      - backup-2: file_a.txt изменён (150 байт), file_b.txt без изменений
 *      - backup-3: добавлен file_c.txt (50 байт)
 *
 *   2. Статистика (stats):
 *      - Все снапшоты имеют ненулевой размер.
 *      - Размеры растут с добавлением данных.
 *
 *   3. Содержимое (ls):
 *      - Каждый снапшот содержит ожидаемые файлы точного размера.
 *      - Файлы не перемешиваются между снапшотами (изоляция).
 *
 *   4. Экспорт (dump):
 *      - Выгрузка отдельного файла даёт точное содержимое.
 *      - Выгрузка снапшота целиком — валидный tar.
 *
 *   5. Обслуживание (forget + check):
 *      - forget --keep-last 2 сохраняет 2 последних снапшота.
 *      - forget --keep-last 1 + check — проверка после удаления.
 *      - unlock на чистом репо.
 *
 * Критерий успеха:
 *   - Все операции возвращают ожидаемые exitCode.
 *   - Размеры и содержимое файлов строго соответствуют ожидаемым.
 *   - Данные разных снапшотов изолированы.
 *   - forget корректно удаляет указанные снапшоты.
 *
 * Важно: тесты выполняются последовательно, деструктивные операции
 *        (forget) идут последними.
 *
 * Требует: restic в PATH.
 */
class SnapshotEndToEndTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;
    /** @var string Путь к restic-репозиторию */
    private string $repoDir;
    /** @var string Путь к тестовым данным */
    private string $dataDir;
    /** @var CommandRunner */
    private CommandRunner $runner;
    /** @var array<string, mixed> Конфигурация репозитория */
    private array $repo;

    /**
     * Ожидаемое содержимое каждого снапшота.
     * @var array<int, array{id: string, short_id: string, time: string, dir1Files: array<string, int>, dir2Files: array<string, int>}>
     */
    private array $snapshots = [];

    protected function setUp(): void
    {
        // Создаём изолированное окружение
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_e2e_' . uniqid();
        $this->repoDir = $this->tmpDir . '/restic-repo';
        $this->dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->repoDir, 0777, true);

        // Две поддиректории для проверки multi-path backup
        mkdir($this->dataDir . '/dir1', 0777, true);
        mkdir($this->dataDir . '/dir2', 0777, true);

        $this->runner = new CommandRunner();

        // Инициализируем репозиторий
        $result = $this->runner->run(
            ['restic', 'init', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        if ($result['exitCode'] !== 0) {
            $this->markTestSkipped('Failed to init restic repo: ' . $result['stderr']);
        }

        $this->repo = [
            'id' => 'e2e-repo',
            'name' => 'E2E Test',
            'type' => 'local',
            'path' => $this->repoDir,
            'password' => null,
        ];

        // === Backup 1: file_a.txt = 100 байт 'A', file_b.txt = 200 байт 'B' ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('A', 100));
        file_put_contents($this->dataDir . '/dir2/file_b.txt', str_repeat('B', 200));

        $this->backup('backup-1');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 100],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 2: file_a.txt изменён (150 байт 'X'), file_b.txt без изменений ===
        file_put_contents($this->dataDir . '/dir1/file_a.txt', str_repeat('X', 150));

        $this->backup('backup-2');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // === Backup 3: добавлен file_c.txt (50 байт 'C') ===
        file_put_contents($this->dataDir . '/dir1/file_c.txt', str_repeat('C', 50));

        $this->backup('backup-3');
        $this->snapshots[] = [
            'id' => '',
            'short_id' => '',
            'time' => '',
            'dir1Files' => ['file_a.txt' => 150, 'file_c.txt' => 50],
            'dir2Files' => ['file_b.txt' => 200],
        ];

        // Получаем реальные ID снапшотов из restic и заполняем ожидаемые данные
        $snapList = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $snapList['exitCode'], 'Snapshot list should succeed');

        $snaps = json_decode($snapList['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(3, $snaps, 'Should have exactly 3 snapshots');

        // Сортируем по времени (от старого к новому)
        usort($snaps, function (array $a, array $b): int {
            return ($a['time'] ?? '') <=> ($b['time'] ?? '');
        });

        foreach ($snaps as $i => $snap) {
            $this->snapshots[$i]['id'] = $snap['id'] ?? '';
            $this->snapshots[$i]['short_id'] = $snap['short_id'] ?? '';
            $this->snapshots[$i]['time'] = $snap['time'] ?? '';
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ========================
    // Stats tests
    // ========================

    /**
     * Проверяет, что все три снапшота имеют ненулевой размер (total_size).
     *
     * Используется `restic stats --mode raw-data` для получения размера
     * уникальных данных каждого снапшота.
     */
    public function testAllSnapshotsHaveNonZeroSize(): void
    {
        $seenIds = [];
        foreach ($this->snapshots as $snap) {
            // Act: запрашиваем статистику по конкретному снапшоту
            $result = $this->runner->run(
                ['restic', 'stats', '--json', '--mode', 'raw-data', '--repo', $this->repoDir, '--insecure-no-password', $snap['id']],
                ['RESTIC_PASSWORD' => '']
            );
            $this->assertSame(0, $result['exitCode'], 'Stats should succeed: ' . $result['stderr']);

            // Парсим JSON (restic 0.19 может вернуть массив из одного элемента)
            $decoded = json_decode($result['stdout'], true);
            $entry = is_array($decoded) ? ($decoded[0] ?? $decoded) : $decoded;
            $this->assertIsArray($entry, "Stats for snapshot " . ($snap['short_id'] ?? '?') . " should be an array");

            $seenIds[] = $snap['short_id'];

            // Assert: total_size присутствует и больше нуля
            $this->assertArrayHasKey('total_size', $entry, "Stats entry should have total_size");
            $this->assertGreaterThan(0, $entry['total_size'], "Snapshot should have non-zero size");
        }

        // Assert: все 3 снапшота обработаны
        $this->assertCount(3, $seenIds, 'Should have stats for all 3 snapshots');
    }

    /**
     * Проверяет, что размеры снапшотов кумулятивно растут.
     *
     * Логика: backup-1 < backup-3 (в backup-3 добавлен file_c.txt).
     * Между backup-1 и backup-2 разница может быть минимальной
     * (изменение размера file_a.txt со 100 на 150 в дедуплицируемом хранилище
     * может не дать значительного прироста total_size),
     * но backup-3 точно больше backup-1 за счёт нового файла.
     */
    public function testSnapshotSizesIncreaseWithNewData(): void
    {
        $sizes = [];

        // Собираем total_size для всех трёх снапшотов
        foreach ($this->snapshots as $snap) {
            $result = $this->runner->run(
                ['restic', 'stats', '--json', '--mode', 'raw-data', '--repo', $this->repoDir, '--insecure-no-password', $snap['id']],
                ['RESTIC_PASSWORD' => '']
            );
            $this->assertSame(0, $result['exitCode'], 'Stats should succeed');
            $decoded = json_decode($result['stdout'], true);
            $entry = is_array($decoded) ? ($decoded[0] ?? $decoded) : $decoded;
            $this->assertIsArray($entry);
            $sizes[$snap['id']] = $entry['total_size'] ?? 0;
        }

        $size1 = $sizes[$this->snapshots[0]['id']];
        $size2 = $sizes[$this->snapshots[1]['id']];
        $size3 = $sizes[$this->snapshots[2]['id']];

        // Базовые проверки: все размеры ненулевые
        $this->assertGreaterThan(0, $size1, 'Backup 1 should have non-zero size');
        $this->assertGreaterThan(0, $size2, 'Backup 2 should have non-zero size');
        $this->assertGreaterThan(0, $size3, 'Backup 3 should have non-zero size');

        // Кумулятивный рост: backup-3 с новым файлом file_c.txt должен быть
        // больше backup-1. Между backup-1 и backup-2 разница может быть
        // незначительной из-за дедупликации (file_a.txt изменился со 100 на 150),
        // поэтому сравниваем backup-1 и backup-3.
        $this->assertGreaterThan($size1, $size3,
            'Backup 3 must be larger than backup 1 (added file_c.txt with new data)'
        );
        }

    // ========================
    // File content tests
    // ========================

    /**
     * Проверяет, что каждый снапшот содержит ожидаемые файлы точного размера.
     *
     * Использует restic ls для получения списка файлов и их размеров,
     * сравнивает с ожидаемыми значениями из $this->snapshots.
     */
    public function testEachSnapshotContainsExpectedFiles(): void
    {
        foreach ($this->snapshots as $snapIndex => $snap) {
            $sid = $snap['short_id'];

            // Проверяем содержимое dir1
            $lsResult = $this->lsPath($snap['id'], $this->dataDir . '/dir1');
            foreach ($snap['dir1Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) dir1 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in dir1 should have size $expectedSize"
                );
            }

            // Проверяем содержимое dir2
            $lsResult = $this->lsPath($snap['id'], $this->dataDir . '/dir2');
            foreach ($snap['dir2Files'] as $filename => $expectedSize) {
                $this->assertArrayHasKey(
                    $filename,
                    $lsResult,
                    "Snapshot $snapIndex ($sid) dir2 should contain $filename"
                );
                $this->assertSame(
                    $expectedSize,
                    $lsResult[$filename],
                    "Snapshot $snapIndex ($sid) $filename in dir2 should have size $expectedSize"
                );
            }
        }
    }

    /**
     * Проверяет изоляцию данных между снапшотами.
     *
     * Утверждает, что:
     *   - backup-1 НЕ содержит file_c.txt (добавлен только в backup-3).
     *   - backup-2 НЕ содержит file_c.txt.
     *   - Размер file_a.txt меняется: 100 → 150 → 150.
     *   - file_b.txt неизменен во всех трёх снапшотах (200 байт).
     */
    public function testFilesAreNotMixedBetweenSnapshots(): void
    {
        $d1 = $this->dataDir . '/dir1';
        $d2 = $this->dataDir . '/dir2';

        // backup-1: file_a.txt = 100 байт, file_c.txt отсутствует
        $files1 = $this->lsPath($this->snapshots[0]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files1);
        $this->assertSame(100, $files1['file_a.txt'], 'backup-1 file_a should be 100 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files1, 'backup-1 should NOT contain file_c.txt');

        // backup-2: file_a.txt = 150 байт, file_c.txt отсутствует
        $files2 = $this->lsPath($this->snapshots[1]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files2);
        $this->assertSame(150, $files2['file_a.txt'], 'backup-2 file_a should be 150 bytes');
        $this->assertArrayNotHasKey('file_c.txt', $files2, 'backup-2 should NOT contain file_c.txt');

        // backup-3: file_a.txt = 150 байт, file_c.txt = 50 байт (оба присутствуют)
        $files3 = $this->lsPath($this->snapshots[2]['id'], $d1);
        $this->assertArrayHasKey('file_a.txt', $files3);
        $this->assertSame(150, $files3['file_a.txt'], 'backup-3 file_a should be 150 bytes');
        $this->assertArrayHasKey('file_c.txt', $files3);
        $this->assertSame(50, $files3['file_c.txt'], 'backup-3 file_c should be 50 bytes');

        // file_b.txt неизменен во всех трёх снапшотах
        foreach ([0, 1, 2] as $i) {
            $files = $this->lsPath($this->snapshots[$i]['id'], $d2);
            $this->assertArrayHasKey('file_b.txt', $files);
            $this->assertSame(200, $files['file_b.txt'], "backup-" . ($i + 1) . " file_b should be 200 bytes");
        }
    }

    // ========================
    // Export tests
    // ========================

    /**
     * Проверяет экспорт одного файла: сравнение содержимого с ожидаемым.
     *
     * Берём backup-3, file_a.txt должен содержать 150 символов 'X'.
     */
    public function testExportSingleFile(): void
    {
        // Act: restic dump file_a.txt из backup-3
        $snapId = $this->snapshots[2]['id'];
        $result = $this->runner->run(
            ['restic', 'dump', $snapId, $this->dataDir . '/dir1/file_a.txt', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: точное содержимое — 150 символов 'X'
        $this->assertSame(0, $result['exitCode'], 'dump file_a.txt should succeed: ' . $result['stderr']);
        $this->assertEquals(str_repeat('X', 150), $result['stdout'], 'file_a.txt should contain exactly 150 X characters');
    }

    /**
     * Проверяет экспорт всего снапшота как tar-архива.
     */
    public function testExportEntireSnapshot(): void
    {
        // Act: restic dump / → tar всего backup-2
        $snapId = $this->snapshots[1]['id'];
        $result = $this->runner->run(
            ['restic', 'dump', $snapId, '/', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );

        // Assert: tar не пустой и содержит сигнатуру "ustar"
        $this->assertSame(0, $result['exitCode'], 'dump / should succeed: ' . $result['stderr']);
        $this->assertGreaterThan(0, strlen($result['stdout']), 'tar output should not be empty');
        $this->assertStringContainsString('ustar', substr($result['stdout'], 257, 10), 'Output should be a valid tar archive');
    }

    // ========================
    // Maintenance tests
    // ========================

    /**
     * Проверяет, что forget --keep-last 2 сохраняет 2 последних снапшота
     * и удаляет самый старый (backup-1).
     *
     * Важно: тест НЕОБРАТИМО удаляет backup-1. Последующие тесты
     *        не должны на него рассчитывать.
     */
    public function testForgetKeepLastKeepsCorrectSnapshots(): void
    {
        // Act: forget --keep-last 2
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '2', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        // Assert: осталось 2 снапшота
        $snapResult = $this->runner->run(
            ['restic', 'snapshots', '--json', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $snaps = json_decode($snapResult['stdout'], true);
        $this->assertIsArray($snaps);
        $this->assertCount(2, $snaps, 'should keep exactly 2 snapshots');

        // Assert: backup-2 и backup-3 сохранены, backup-1 удалён
        $remainingIds = array_map(fn($s) => $s['id'] ?? '', $snaps);
        $this->assertContains($this->snapshots[1]['id'], $remainingIds, 'backup-2 should be kept');
        $this->assertContains($this->snapshots[2]['id'], $remainingIds, 'backup-3 should be kept');
        $this->assertNotContains($this->snapshots[0]['id'], $remainingIds, 'backup-1 should be removed');
    }

    /**
     * Проверяет, что после forget --keep-last 1 + check репозиторий
     * остаётся целостным.
     *
     * Зависит от состояния после testForgetKeepLastKeepsCorrectSnapshots
     * (осталось 2 снапшота). После forget останется 1, check должен пройти.
     */
    public function testForgetThenCheck(): void
    {
        // Шаг 1: forget --keep-last 1 (оставляем только последний)
        $result = $this->runner->run(
            ['restic', 'forget', '--keep-last', '1', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'forget should succeed: ' . $result['stderr']);

        // Шаг 2: check после forget
        $result = $this->runner->run(
            ['restic', 'check', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'check after forget should succeed: ' . $result['stderr']);
    }

    /**
     * Проверяет, что unlock на чистом репозитории завершается успешно.
     */
    public function testUnlockSucceeds(): void
    {
        $result = $this->runner->run(
            ['restic', 'unlock', '--repo', $this->repoDir, '--insecure-no-password'],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], 'unlock on clean repo should succeed');
    }

    // ========================
    // Helpers
    // ========================

    private function backup(string $tag): void
    {
        $result = $this->runner->run(
            ['restic', 'backup', '--repo', $this->repoDir, '--insecure-no-password', '--tag', $tag, $this->dataDir],
            ['RESTIC_PASSWORD' => '']
        );
        $this->assertSame(0, $result['exitCode'], "Backup '$tag' should succeed: " . $result['stderr']);
    }

    /**
     * @return array<string, int>
     */
    private function lsPath(string $snapId, string $path): array
    {
        $result = $this->runner->run(
            ['restic', 'ls', '--json', '--repo', $this->repoDir, '--insecure-no-password', $snapId, $path],
            ['RESTIC_PASSWORD' => '']
        );

        $this->assertSame(0, $result['exitCode'], "ls $path should succeed: " . ($result['stderr'] ?? ''));

        $files = [];
        $lines = explode("\n", trim($result['stdout']));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'null') {
                continue;
            }
            $entry = json_decode($line, true);
            if (is_array($entry) && !empty($entry['name']) && ($entry['type'] ?? '') === 'file') {
                $files[$entry['name']] = $entry['size'] ?? 0;
            }
        }
        return $files;
    }

    private function shortId(string $id): string
    {
        return substr($id, 0, 8);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
