<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Cache\CacheManager;
use App\Cache\CacheScope;
use App\Process\TaskPostProcessor;
use App\Process\TspTaskManager;
use App\Restic\ResticTaskService;
use App\Storage\ContentCacheStorage;
use App\Storage\ContentResolver;
use App\Storage\ContentTargets;
use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест резолвера контента (ContentResolver): read/schedule/reconcile.
 *
 * Цель: проверить, что чтение, постановка задачи и постобработка разделены:
 *       read() не пишет и не запускает; schedule() ставит задачу и пишет
 *       обещание (и не ставит вторую при активной); reconcile() идемпотентно
 *       доводит готовый вывод до контента.
 *
 * Сценарий: реальный ContentCacheStorage на сессионной области; TspTaskManager
 *       и RepositoryStorage замоканы; постобработка — настоящая (op snapshots,
 *       реальный JSON-вывод перехваченного catResult).
 *
 * Критерий успеха: все assert проходят.
 */
class ContentResolverTest extends TestCase
{
    private CacheManager $cache;
    private ContentCacheStorage $storage;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->cache = new CacheManager('session');
        $this->storage = new ContentCacheStorage($this->cache, 600, 31536000);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    /**
     * @param array<string, mixed>|null $repo
     */
    private function resolver(TspTaskManager $tasks, ?RepositoryStorage $repos = null, ?ResticTaskService $restic = null): ContentResolver
    {
        $restic ??= $this->createMock(ResticTaskService::class);
        $repos ??= $this->createMock(RepositoryStorage::class);

        return new ContentResolver(
            $this->storage,
            $tasks,
            $restic,
            new TaskPostProcessor($tasks, $this->storage),
            $repos
        );
    }

    private function publicTarget(): \App\Storage\ContentTarget
    {
        return ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);
    }

    /** read(): готовое значение → ready. */
    public function testReadReady(): void
    {
        $target = $this->publicTarget();
        $this->storage->put($target, [['id' => 'a']]);

        $read = $this->resolver($this->createMock(TspTaskManager::class))->read($target);

        $this->assertSame('ready', $read['state']);
        $this->assertSame([['id' => 'a']], $read['value']);
    }

    /** read(): нет записи → absent. */
    public function testReadAbsent(): void
    {
        $read = $this->resolver($this->createMock(TspTaskManager::class))->read($this->publicTarget());

        $this->assertSame('absent', $read['state']);
    }

    /** read(): обещание + активная задача → waiting. */
    public function testReadWaiting(): void
    {
        $target = $this->publicTarget();
        $label = 'alice#snapshotspub10123456789abcdef';
        $this->storage->promise($target, $label);

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('findByLabel')->with($label)->willReturn(['id' => 5, 'state' => 'running', 'label' => $label]);

        $read = $this->resolver($tasks)->read($target);

        $this->assertSame('waiting', $read['state']);
        $this->assertSame('running', $read['task_state']);
    }

    /** read(): обещание + завершённая задача → ready_to_process. */
    public function testReadReadyToProcess(): void
    {
        $target = $this->publicTarget();
        $label = 'alice#snapshotspub10123456789abcdef';
        $this->storage->promise($target, $label);

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('findByLabel')->with($label)->willReturn(['id' => 5, 'state' => 'finished', 'label' => $label]);

        $read = $this->resolver($tasks)->read($target);

        $this->assertSame('ready_to_process', $read['state']);
    }

    /** read(): задача пропала из очереди → absent (страница предложит запрос). */
    public function testReadPromiseWithoutJobIsAbsent(): void
    {
        $target = $this->publicTarget();
        $this->storage->promise($target, 'alice#snapshotspub10123456789abcdef');

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('findByLabel')->willReturn(null);

        $read = $this->resolver($tasks)->read($target);

        $this->assertSame('absent', $read['state']);
    }

    /** schedule(): ставит задачу и записывает обещание. */
    public function testScheduleStartsTaskAndWritesPromise(): void
    {
        $target = $this->publicTarget();
        $label = 'alice#snapshotspub10123456789abcdef';

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('listActiveFor')->willReturn([]);

        $restic = $this->createMock(ResticTaskService::class);
        $restic->expects($this->once())
            ->method('startListSnapshots')
            ->willReturn(['label' => $label, 'id' => 7]);

        $started = $this->resolver($tasks, null, $restic)->schedule($target, ['id' => 'pub1']);

        $this->assertSame($label, $started['label']);
        $this->assertSame($label, $this->storage->read($target)->promise);
    }

    /** schedule(): активная задача уже есть — вторую не ставим, возвращаем её метку. */
    public function testScheduleDoesNotStartSecondTaskWhenActive(): void
    {
        $target = $this->publicTarget();

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('listActiveFor')->willReturn([
            ['id' => 9, 'state' => 'running', 'label' => 'bob#snapshotspub10123456789abcdef', 'objectId' => null],
        ]);

        $restic = $this->createMock(ResticTaskService::class);
        $restic->expects($this->never())->method('startListSnapshots');

        $started = $this->resolver($tasks, null, $restic)->schedule($target, ['id' => 'pub1']);

        $this->assertSame('bob#snapshotspub10123456789abcdef', $started['label']);
        $this->assertNull($this->storage->read($target), 'no promise written when active task already exists');
    }

    /** reconcile(): завершённый вывод разобран в контент, обещание снято; повтор — no-op. */
    public function testReconcileWritesContentAndIsIdempotent(): void
    {
        $target = $this->publicTarget();
        $label = 'alice#snapshotspub10123456789abcdef';
        $this->storage->promise($target, $label);

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('findByLabel')->with($label)->willReturn(['id' => 5, 'state' => 'finished', 'label' => $label]);
        $tasks->method('catResult')->with('alice', $label, true)->willReturn([
            'exitCode' => 0,
            'output' => '[{"id":"a"},{"id":"b"}]',
            'stderr' => '',
        ]);

        $resolver = $this->resolver($tasks);

        // Act: первая обработка.
        $first = $resolver->reconcile($target);

        // Assert: контент записан, обещание снято.
        $this->assertTrue($first);
        $entry = $this->storage->read($target);
        $this->assertSame([['id' => 'a'], ['id' => 'b']], $entry->value);
        $this->assertNull($entry->promise);

        // Act + Assert: повторный вызов — no-op (идемпотентность).
        $this->assertFalse($resolver->reconcile($target));
    }

    /** reconcile(): ожидание/ошибка/пусто — no-op. */
    public function testReconcileNoopForNonReadyStates(): void
    {
        $running = $this->publicTarget();
        $runningLabel = 'a#snapshotsr10123456789abcdef';
        $this->storage->promise($running, $runningLabel);
        $tasksRunning = $this->createMock(TspTaskManager::class);
        $tasksRunning->method('findByLabel')->willReturn(['id' => 1, 'state' => 'running', 'label' => $runningLabel]);
        $this->assertFalse($this->resolver($tasksRunning)->reconcile($running));

        $failed = $this->publicTarget();
        $this->storage->fail($failed, 'boom');
        $this->assertFalse($this->resolver($this->createMock(TspTaskManager::class))->reconcile($failed));

        $absent = ContentTargets::forSnapshots(['id' => 'none', 'category' => 'public']);
        $this->assertFalse($this->resolver($this->createMock(TspTaskManager::class))->reconcile($absent));
    }

    /** reconcileByLabel(): по завершённой задаче находит целевой объект и обрабатывает. */
    public function testReconcileByLabelResolvesRepoAndProcesses(): void
    {
        $repo = ['id' => 'pub1', 'category' => 'public'];
        $target = ContentTargets::forSnapshots($repo);
        $label = ContentTargets::labelOf($target, 'alice');
        $this->storage->promise($target, $label);

        $tasks = $this->createMock(TspTaskManager::class);
        $tasks->method('findByLabel')->with($label)->willReturn(['id' => 5, 'state' => 'finished', 'label' => $label]);
        $tasks->method('catResult')->willReturn(['exitCode' => 0, 'output' => '[{"id":"a"}]', 'stderr' => '']);

        $repos = $this->createMock(RepositoryStorage::class);
        $repos->method('loadAll')->with('alice')->willReturn([$repo]);

        $this->assertTrue($this->resolver($tasks, $repos)->reconcileByLabel($label));
        $this->assertSame([['id' => 'a']], $this->storage->read($target)->value);
    }

    /**
     * targetForLabel: для списка снепшотов ПРИВАТНОГО репо берёт User-область
     * (а не жёсткий System из ContentTargets::fromLabel) — иначе чтение шло бы
     * из пустой системной области и состояние всегда было бы absent.
     */
    public function testTargetForLabelUsesRepoCategoryScope(): void
    {
        $repo = ['id' => 'priv1', 'category' => 'private'];
        $label = ContentTargets::labelOf(ContentTargets::forSnapshots($repo), 'alice');

        $repos = $this->createMock(RepositoryStorage::class);
        $repos->method('loadAll')->with('alice')->willReturn([$repo]);

        $target = $this->resolver($this->createMock(TspTaskManager::class), $repos)->targetForLabel($label);

        $this->assertNotNull($target);
        $this->assertSame(CacheScope::User, $target->scope);
        $this->assertSame('repos/priv1/snapshots.json', $target->cacheKey());
    }

    /** targetForLabel: метка несвязанной операции → null (нет целевого объекта кеша). */
    public function testTargetForLabelReturnsNullForUnrelatedOp(): void
    {
        $target = $this->resolver($this->createMock(TspTaskManager::class))->targetForLabel('alice#backupr10123456789abcdef');

        $this->assertNull($target);
    }
}
