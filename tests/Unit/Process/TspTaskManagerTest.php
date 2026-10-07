<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Process;

use App\Core\App;
use App\Process\TspClient;
use App\Process\TspTaskManager;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест TspTaskManager (метки, фильтрация, разбор, очередь).
 *
 * Цель: проверить построение метки через TaskLabel, фильтрацию по пользователю,
 *       дедупликацию по репозиторию (по всем пользователям), описание задачи,
 *       позицию в очереди, отмену/подъём и мемоизацию списка на запрос.
 *
 * Сценарий: TspClient замокан; App::resetCaches() сбрасывает область текущего
 * запроса перед каждым тестом, чтобы мемоизация не «протекала».
 *
 * Критерий успеха: все assert проходят.
 */
class TspTaskManagerTest extends TestCase
{
    protected function setUp(): void
    {
        App::resetCaches();
    }

    protected function tearDown(): void
    {
        App::resetCaches();
    }

    /** start() строит метку вида <username>#<op><repoId><rand16>. */
    public function testStartBuildsLabelWithOpAndRepo(): void
    {
        $captured = null;

        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())
            ->method('enqueue')
            ->willReturnCallback(function (string $label) use (&$captured): array {
                $captured = $label;
                return ['id' => 5, 'label' => $label];
            });

        $manager = new TspTaskManager($mock);
        $result = $manager->start('alice', 'check', 'repo1', ['/bin/echo', 'hi']);

        $this->assertSame(5, $result['id']);
        $this->assertNotNull($captured);
        $this->assertStringStartsWith('alice#checkrepo1', $captured);
        $this->assertTrue($manager->isValidLabel($captured));
    }

    /** listForUser возвращает только задачи пользователя. */
    public function testListForUserFiltersByUsername(): void
    {
        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())->method('list')->willReturn([
            $this->job(1, 'alice#checkr10123456789abcdef'),
            $this->job(2, 'bob#checkr10123456789abcdef'),
            $this->job(3, null),
        ]);

        $result = (new TspTaskManager($mock))->listForUser('alice', false);

        $this->assertCount(1, $result);
        $this->assertSame('alice#checkr10123456789abcdef', $result[0]['label']);
    }

    /** privileged = true возвращает все задачи. */
    public function testListForUserPrivilegedReturnsAll(): void
    {
        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())->method('list')->willReturn([
            $this->job(1, 'alice#checkr10123456789abcdef'),
            $this->job(2, 'bob#checkr10123456789abcdef'),
        ]);

        $result = (new TspTaskManager($mock))->listForUser('alice', true);
        $this->assertCount(2, $result);
    }

    /** assertAccess: своя задача — true, чужая — false, для privileged — true. */
    public function testAssertAccessRules(): void
    {
        $manager = new TspTaskManager($this->createMock(TspClient::class));

        $this->assertTrue($manager->assertAccess('alice', 'alice#checkr10123456789abcdef', false));
        $this->assertFalse($manager->assertAccess('alice', 'bob#checkr10123456789abcdef', false));
        $this->assertTrue($manager->assertAccess('alice', 'bob#checkr10123456789abcdef', true));
        $this->assertFalse($manager->assertAccess('alice', 'invalid-label', false));
    }

    /** describe даёт заголовок из i18n и repoId. */
    public function testDescribe(): void
    {
        $manager = new TspTaskManager($this->createMock(TspClient::class));

        $described = $manager->describe('alice#snapshotsr10123456789abcdef');
        $this->assertNotNull($described);
        $this->assertSame('snapshots', $described['op']);
        $this->assertSame('r1', $described['repoId']);
        $this->assertNotEmpty($described['title']);

        $this->assertNull($manager->describe('garbage'));
    }

    /** listActiveFor находит задачи по op+repoId у всех пользователей (дедуп). */
    public function testListActiveForMatchesAcrossUsers(): void
    {
        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())->method('list')->willReturn([
            $this->job(1, 'alice#snapshotsr10123456789abcdef'),
            $this->job(2, 'bob#snapshotsr10123456789abcdef'),
            $this->job(3, 'bob#snapshotsr20123456789abcdef'),
            $this->job(4, 'alice#pruner10123456789abcdef'),
        ]);

        $manager = new TspTaskManager($mock);
        $result = $manager->listActiveFor('snapshots', 'r1');

        $this->assertCount(2, $result);
        $this->assertSame(['alice', 'bob'], array_column($result, 'username'));

        // Репозиторный stats не пересекается со snapstats.
        $this->assertSame([], $manager->listActiveFor('stats', 'r1'));
    }

    /** queuePosition считает задачи в очереди перед данной. */
    public function testQueuePosition(): void
    {
        $mock = $this->createMock(TspClient::class);
        $mock->expects($this->once())->method('list')->willReturn([
            ['id' => 1, 'state' => 'running', 'command' => 'x', 'label' => 'alice#checkr10123456789abcdef', 'output' => null, 'errorlevel' => null],
            ['id' => 2, 'state' => 'queued', 'command' => 'x', 'label' => 'bob#pruner20123456789abcdef', 'output' => null, 'errorlevel' => null],
            ['id' => 3, 'state' => 'queued', 'command' => 'x', 'label' => 'bob#pruner20123456789abcdee', 'output' => null, 'errorlevel' => null],
        ]);

        $manager = new TspTaskManager($mock);

        $this->assertSame(1, $manager->queuePosition('bob#pruner20123456789abcdef'));
        $this->assertSame(2, $manager->queuePosition('bob#pruner20123456789abcdee'));
        $this->assertNull($manager->queuePosition('nobody#checkr10123456789abcdef'));
    }

    /** cancel и promote вызывают нужные методы TspClient. */
    public function testCancelAndPromote(): void
    {
        $mock = $this->createMock(TspClient::class);
        $mock->method('list')->willReturn([
            $this->job(7, 'alice#checkr10123456789abcdef'),
        ]);
        $mock->expects($this->once())->method('kill')->with(7);
        $mock->expects($this->once())->method('promote')->with(7);

        $manager = new TspTaskManager($mock);

        $this->assertTrue($manager->cancel('alice', 'alice#checkr10123456789abcdef', false));
        $this->assertTrue($manager->promote('alice', 'alice#checkr10123456789abcdef', false));
        $this->assertFalse($manager->cancel('alice', 'bob#checkr10123456789abcdef', false));
    }

    /**
     * Мемоизация: несколько запросов за один рендер дают один вызов list();
     * после App::resetCaches() кеш области запроса пуст и list() вызывается вновь.
     */
    public function testListIsMemoizedPerRequest(): void
    {
        $first = $this->createMock(TspClient::class);
        $first->expects($this->once())->method('list')->willReturn([
            $this->job(1, 'alice#checkr10123456789abcdef'),
        ]);

        $manager = new TspTaskManager($first);
        $manager->findByLabel('alice#checkr10123456789abcdef');
        $manager->findByLabel('alice#checkr10123456789abcdef');

        App::resetCaches();

        $second = $this->createMock(TspClient::class);
        $second->expects($this->once())->method('list')->willReturn([]);

        $manager2 = new TspTaskManager($second);
        $this->assertNull($manager2->findByLabel('alice#checkr10123456789abcdef'));
    }

    /**
     * @return array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}
     */
    private function job(int $id, ?string $label): array
    {
        return [
            'id' => $id,
            'state' => 'finished',
            'command' => 'cmd',
            'label' => $label,
            'output' => null,
            'errorlevel' => null,
        ];
    }
}
