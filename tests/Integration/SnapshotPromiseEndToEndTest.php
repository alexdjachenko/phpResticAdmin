<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Cache\CacheManager;
use App\Core\App;
use App\Process\TaskPostProcessor;
use App\Process\TspClient;
use App\Process\TspTaskManager;
use App\Restic\CommandRunner;
use App\Restic\RepositoryService;
use App\Restic\ResticTaskService;
use App\Restic\SnapshotService;
use App\Storage\ContentCacheStorage;
use App\Storage\ContentResolver;
use App\Storage\ContentTargets;
use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный end-to-end тест модели «обещание контента».
 *
 * Цель: доказать на живом restic/tsp цепочку schedule → waiting → reconcile →
 *       ready для списка снепшотов и статистики, а также обратный путь «метка
 *       → целевой объект» и «липкую» ошибку.
 *
 * Сценарий:
 *   1. Инициализируется временный restic-репозиторий, делаются 2 backup.
 *   2. schedule списка → реальная tsp-задача `restic snapshots --json`; read()
 *      даёт waiting; поллинг (неблокирующий) до завершения.
 *   3. reconcile → read → ready; контент равен списку снепшотов, обещания нет.
 *   4. Обратный путь: ContentTargets::fromLabel(label) даёт ту же цель, а
 *      reconcileByLabel(label) доводит до готовности (как дашборд).
 *   5. snapstats: schedule → reconcile → в кеше stats с total_size > 0.
 *   6. Негативный путь: несуществующий snapId → read() → failed, контент не
 *      записан; повторный read() не перезапускает задачу (ошибка липкая).
 *
 * Критерий успеха: все assert проходят.
 *
 * Требует: restic и tsp (task-spooler) в PATH (тест запускается только в CI).
 */
class SnapshotPromiseEndToEndTest extends TestCase
{
    /** @var string */
    private string $tmpDir;
    /** @var array<string, mixed> */
    private array $repo;
    /** @var CommandRunner */
    private CommandRunner $runner;
    /** @var TspTaskManager */
    private TspTaskManager $tasks;
    /** @var ContentCacheStorage */
    private ContentCacheStorage $storage;
    /** @var ContentResolver */
    private ContentResolver $resolver;

    protected function setUp(): void
    {
        App::resetCaches();

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_SESSION['auth_user'] = 'tester';

        $this->runner = new CommandRunner();

        if ($this->runner->run(['tsp', '-V'])['exitCode'] !== 0) {
            $this->markTestSkipped('tsp (task-spooler) is not available');
        }

        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_promise_' . uniqid();
        $repoDir = $this->tmpDir . '/restic-repo';
        $dataDir = $this->tmpDir . '/data';
        mkdir($this->tmpDir, 0777, true);
        mkdir($repoDir, 0777, true);
        mkdir($dataDir, 0777, true);
        file_put_contents($dataDir . '/file_a.txt', str_repeat('A', 100));
        file_put_contents($dataDir . '/file_b.txt', str_repeat('B', 200));

        $this->repo = [
            'id' => 'promise-repo',
            'name' => 'Promise E2E',
            'type' => 'local',
            'local_path' => $repoDir,
            'password' => null,
            'category' => 'public',
        ];

        $repoService = new RepositoryService($this->runner);
        if (!$repoService->init($this->repo)['ok']) {
            $this->markTestSkipped('Failed to init restic repo');
        }
        $firstBackup = $repoService->backupSync($this->repo, [$dataDir]);
        if (!$firstBackup['ok']) {
            $this->markTestSkipped('Failed to create backup');
        }
        $secondBackup = $repoService->backupSync($this->repo, [$dataDir]);
        if (!$secondBackup['ok']) {
            $this->markTestSkipped('Failed to create second backup');
        }

        $tspDir = $this->tmpDir . '/tsp';
        mkdir($tspDir, 0777, true);
        $tsp = new TspClient($this->runner, $this->tmpDir, $tspDir . '/socket');

        $this->tasks = new TspTaskManager($tsp);
        $this->storage = new ContentCacheStorage(new CacheManager('session'), 600, 31536000);
        $this->resolver = new ContentResolver(
            $this->storage,
            $this->tasks,
            new ResticTaskService($this->tasks),
            new TaskPostProcessor($this->tasks, $this->storage),
            new RepositoryStorage()
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user']);
        App::resetCaches();
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        $this->removeDir($this->tmpDir);
    }

    /**
     * Поллинг завершения задача без блокировки: read() короткий, между шагами
     * сбрасываем мемоизацию списка tsp.
     */
    private function waitFinished(string $label, int $timeoutSeconds = 30): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            App::resetCaches();
            $job = $this->tasks->findByLabel($label);
            if ($job !== null && in_array($job['state'], ['finished', 'skipped', 'unknown'], true)) {
                return;
            }
            usleep(200000);
        }
        $this->fail('task did not finish in time: ' . $label);
    }

    /** schedule → waiting → reconcileByLabel → ready (список снепшотов). */
    public function testSnapshotsScheduleThenReconcileByLabel(): void
    {
        // Arrange
        $target = ContentTargets::forSnapshots($this->repo);
        $this->assertSame('absent', $this->resolver->read($target)['state']);

        // Act: явная операция старта.
        $started = $this->resolver->schedule($target, $this->repo);

        // Assert: задача поставлена, записано обещание.
        $this->assertGreaterThanOrEqual(0, $started['id']);
        $readAfterSchedule = $this->resolver->read($target);
        $this->assertContains($readAfterSchedule['state'], ['waiting', 'ready_to_process']);
        $this->assertSame($started['label'], $readAfterSchedule['promise']);

        $this->waitFinished($started['label']);

        // Обратный путь: метка → целевой объект.
        $restored = ContentTargets::fromLabel($started['label']);
        $this->assertNotNull($restored);
        $this->assertSame($target->cacheKey(), $restored->cacheKey());

        // Act: постобработка (как дашборд по завершённой задаче).
        $this->assertTrue($this->resolver->reconcileByLabel($started['label']));

        // Assert: контент записан, обещание снято.
        $ready = $this->resolver->read($target);
        $this->assertSame('ready', $ready['state']);
        $this->assertNull($ready['promise']);
        $this->assertCount(2, $ready['value']);

        // Идемпотентность: повторная постобработка — no-op.
        $this->assertFalse($this->resolver->reconcile($target));
    }

    /** snapstats: schedule → reconcile → в кеше статистика с total_size > 0. */
    public function testSnapshotStatsScheduleThenReconcile(): void
    {
        // Arrange
        $snapshots = (new SnapshotService($this->runner))->listSnapshots($this->repo);
        $this->assertCount(2, $snapshots);
        $snapId = (string) $snapshots[0]['id'];

        $target = ContentTargets::forStats((string) $this->repo['id'], $snapId);

        // Act
        $started = $this->resolver->schedule($target, $this->repo);
        $this->assertGreaterThanOrEqual(0, $started['id']);
        $this->waitFinished($started['label']);

        $this->assertTrue($this->resolver->reconcile($target));

        // Assert
        $ready = $this->resolver->read($target);
        $this->assertSame('ready', $ready['state']);
        $this->assertGreaterThan(0, (int) ($ready['value']['total_size'] ?? 0));
    }

    /** Негативный путь: несуществующий snapId → failed, ошибка липкая. */
    public function testFailedTaskProducesStickyError(): void
    {
        // Arrange: статистика для несуществующего снепшота завершится с ошибкой restic.
        $target = ContentTargets::forStats((string) $this->repo['id'], str_repeat('0', 64));

        // Act
        $started = $this->resolver->schedule($target, $this->repo);
        $this->assertGreaterThanOrEqual(0, $started['id']);
        $this->waitFinished($started['label']);
        $this->assertFalse($this->resolver->reconcile($target));

        // Assert: ошибка зафиксирована и липкая.
        $failed = $this->resolver->read($target);
        $this->assertSame('failed', $failed['state']);
        $this->assertNull($failed['value']);
        $this->assertNotEmpty($failed['error']);

        // Повторный read() не перезапускает задачу (ошибка липкая).
        $this->assertSame('failed', $this->resolver->read($target)['state']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
