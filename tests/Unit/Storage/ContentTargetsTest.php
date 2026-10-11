<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Cache\CacheScope;
use App\Storage\ContentTarget;
use App\Storage\ContentTargets;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест резолвера целевых объектов (ContentTargets).
 *
 * Цель: проверить построение целевого объекта по репозиторию/снепшоту (ключ,
 *       область кеша, op) и round-trip «объект → метка → объект» (обе стороны
 *       вывода «метка <-> кеш»).
 *
 * Сценарий:
 *   - forSnapshots: публичный репо → System, приватный → User; ключ repos/<id>;
 *   - forStats: статистика всегда System; ключ snapshots/<snapId>;
 *   - round-trip: fromLabel(labelOf(target)) восстанавливает тот же объект.
 *
 * Критерий успеха: все assert проходят.
 */
class ContentTargetsTest extends TestCase
{
    /** Публичный репозиторий → System-область, ключ repos/<id>/snapshots.json. */
    public function testForSnapshotsPublicUsesSystemScope(): void
    {
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $this->assertSame(ContentTarget::KIND_REPO_SNAPSHOTS, $target->kind);
        $this->assertSame('pub1', $target->objectId);
        $this->assertSame('pub1', $target->repoId);
        $this->assertSame(CacheScope::System, $target->scope);
        $this->assertSame('snapshots', $target->op());
        $this->assertSame('repos/pub1/snapshots.json', $target->cacheKey());
    }

    /** Приватный/сессионный репозиторий → User-область. */
    public function testForSnapshotsPrivateUsesUserScope(): void
    {
        $private = ContentTargets::forSnapshots(['id' => 'priv1', 'category' => 'private']);
        $session = ContentTargets::forSnapshots(['id' => 'sess1', 'category' => 'session']);

        $this->assertSame(CacheScope::User, $private->scope);
        $this->assertSame(CacheScope::User, $session->scope);
    }

    /** Статистика снепшота — глобальна: всегда System, ключ по snapId. */
    public function testForStatsUsesSystemScopeAndSnapKey(): void
    {
        $target = ContentTargets::forStats('repo1', 'snap123');

        $this->assertSame(ContentTarget::KIND_SNAPSHOT_STATS, $target->kind);
        $this->assertSame('snap123', $target->objectId);
        $this->assertSame('repo1', $target->repoId);
        $this->assertSame(CacheScope::System, $target->scope);
        $this->assertSame('snapstats', $target->op());
        $this->assertSame('snapshots/snap123/stats.json', $target->cacheKey());
    }

    /** Round-trip для списка снепшотов: объект → метка → объект. */
    public function testSnapshotsLabelRoundTrip(): void
    {
        $target = ContentTargets::forSnapshots(['id' => 'pub1', 'category' => 'public']);

        $label = ContentTargets::labelOf($target, 'alice');
        $this->assertStringStartsWith('alice#snapshots', $label);

        $restored = ContentTargets::fromLabel($label);

        $this->assertNotNull($restored);
        $this->assertEquals($target, $restored);
        $this->assertSame($target->cacheKey(), $restored->cacheKey());
    }

    /** Round-trip для статистики: snapId переносится через метку. */
    public function testStatsLabelRoundTrip(): void
    {
        $target = ContentTargets::forStats('repo1', 'snap123');

        $label = ContentTargets::labelOf($target, 'bob');
        $restored = ContentTargets::fromLabel($label);

        $this->assertNotNull($restored);
        $this->assertEquals($target, $restored);
        $this->assertSame('snap123', $restored->objectId);
        $this->assertSame('snapshots/snap123/stats.json', $restored->cacheKey());
    }

    /** Метки несвязанных операций целевого объекта не дают. */
    public function testFromLabelIgnoresUnrelatedOps(): void
    {
        $this->assertNull(ContentTargets::fromLabel('alice#backuprepo10123456789abcdef'));
        $this->assertNull(ContentTargets::fromLabel('alice#checkrepo10123456789abcdef'));
        $this->assertNull(ContentTargets::fromLabel('garbage'));
    }

    /** snapstats без objectId не даёт цель (ключ по snapId не вычислить). */
    public function testFromLabelRequiresObjectIdForStats(): void
    {
        $this->assertNull(ContentTargets::fromLabel('alice#snapstatsrepo10123456789abcdef'));
    }
}
