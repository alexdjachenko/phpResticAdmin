<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Helpers;

use App\Helpers\RepositoryPath;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест RepositoryPath (типоспецифичные расположения репозиториев).
 *
 * Цель: проверить normalize(), toResticLocation(), isWithinRoots(),
 *       firstDisallowedBackupPath() и localRepoAllowed().
 *
 * Критерий успеха: все assert проходят.
 */
class RepositoryPathTest extends TestCase
{
    // === normalize() ===

    /** local relative → префикс repo_base_dir. */
    public function testNormalizeLocalRelativePrefixesBaseDir(): void
    {
        $this->assertSame('/backups/my-repo', RepositoryPath::normalize('local', 'my-repo'));
    }

    /** local relative с кастомным repo_base_dir. */
    public function testNormalizeLocalRelativeWithCustomBaseDir(): void
    {
        $this->assertSame('/data/my-repo', RepositoryPath::normalize('local', 'my-repo', '/data'));
    }

    /** local absolute → без изменений. */
    public function testNormalizeLocalAbsoluteUnchanged(): void
    {
        $this->assertSame('/srv/repo', RepositoryPath::normalize('local', '/srv/repo'));
    }

    /** s3 bucket → без префикса и без ведущих слэшей. */
    public function testNormalizeS3BucketStripsSlashes(): void
    {
        $this->assertSame('my-bucket/restic', RepositoryPath::normalize('s3', '/my-bucket/restic'));
    }

    /** s3 legacy full scheme → без изменений. */
    public function testNormalizeS3LegacySchemeKept(): void
    {
        $this->assertSame('s3:https://s3.example.com/bucket', RepositoryPath::normalize('s3', 's3:https://s3.example.com/bucket'));
    }

    /** sftp → снятие пробелов и схемы. */
    public function testNormalizeSftpStripsSchemeAndWhitespace(): void
    {
        $this->assertSame('user@host:/srv/repo', RepositoryPath::normalize('sftp', '  sftp:user@host:/srv/repo  '));
    }

    /** rest → снятие схемы. */
    public function testNormalizeRestStripsScheme(): void
    {
        $this->assertSame('http://host:8000/', RepositoryPath::normalize('rest', 'rest:http://host:8000/'));
    }

    // === toResticLocation() ===

    /** local → local_path. */
    public function testToResticLocationLocal(): void
    {
        $this->assertSame('/backups/repo', RepositoryPath::toResticLocation(['type' => 'local', 'local_path' => '/backups/repo']));
    }

    /** local legacy path (без local_path) → path. */
    public function testToResticLocationLocalLegacyPathFallback(): void
    {
        $this->assertSame('/backups/repo', RepositoryPath::toResticLocation(['type' => 'local', 'path' => '/backups/repo']));
    }

    /** s3 s3_bucket без endpoint → AWS S3. */
    public function testToResticLocationS3Aws(): void
    {
        $this->assertSame('s3:s3.amazonaws.com/my-bucket', RepositoryPath::toResticLocation(['type' => 's3', 's3_bucket' => 'my-bucket']));
    }

    /** s3 s3_bucket + endpoint → s3:{endpoint}/{bucket}. */
    public function testToResticLocationS3WithEndpoint(): void
    {
        $this->assertSame(
            's3:https://s3.example.com/my-bucket',
            RepositoryPath::toResticLocation(['type' => 's3', 's3_bucket' => 'my-bucket', 'env' => ['AWS_ENDPOINT' => 'https://s3.example.com']])
        );
    }

    /** s3 endpoint без схемы → добавляется https://. */
    public function testToResticLocationS3EndpointWithoutScheme(): void
    {
        $this->assertSame(
            's3:https://s3.example.com/my-bucket',
            RepositoryPath::toResticLocation(['type' => 's3', 's3_bucket' => 'my-bucket', 'env' => ['AWS_ENDPOINT' => 's3.example.com']])
        );
    }

    /** s3 legacy path (без s3_bucket) → трактуется как bucket. */
    public function testToResticLocationS3LegacyPathFallback(): void
    {
        $this->assertSame('s3:s3.amazonaws.com/my-bucket', RepositoryPath::toResticLocation(['type' => 's3', 'path' => 'my-bucket']));
    }

    /** s3 legacy full scheme → используется как есть. */
    public function testToResticLocationS3LegacyScheme(): void
    {
        $this->assertSame('s3:https://s3.example.com/b', RepositoryPath::toResticLocation(['type' => 's3', 's3_bucket' => 's3:https://s3.example.com/b']));
    }

    /** sftp → sftp:{value}. */
    public function testToResticLocationSftp(): void
    {
        $this->assertSame('sftp:user@host:/srv/repo', RepositoryPath::toResticLocation(['type' => 'sftp', 'sftp_path' => 'user@host:/srv/repo']));
    }

    /** rest → rest:{value}. */
    public function testToResticLocationRest(): void
    {
        $this->assertSame('rest:http://host:8000/', RepositoryPath::toResticLocation(['type' => 'rest', 'rest_url' => 'http://host:8000/']));
    }

    // === isWithinRoots() ===

    /** Точное совпадение с корнем. */
    public function testIsWithinRootsExactMatch(): void
    {
        $this->assertTrue(RepositoryPath::isWithinRoots('/sources', ['/sources']));
    }

    /** Вложенный путь. */
    public function testIsWithinRootsNestedPath(): void
    {
        $this->assertTrue(RepositoryPath::isWithinRoots('/sources/data', ['/sources']));
    }

    /** Похожий, но другой корень (/backups2) — не проходит. */
    public function testIsWithinRootsSiblingRejected(): void
    {
        $this->assertFalse(RepositoryPath::isWithinRoots('/backups2/x', ['/backups']));
    }

    /** Несвязанный путь — не проходит. */
    public function testIsWithinRootsUnrelatedRejected(): void
    {
        $this->assertFalse(RepositoryPath::isWithinRoots('/etc', ['/backups']));
    }

    /** Корень / разрешает всё. */
    public function testIsWithinRootsRootAllowsAll(): void
    {
        $this->assertTrue(RepositoryPath::isWithinRoots('/anything', ['/']));
    }

    /** Пустой список корней = без ограничений. */
    public function testIsWithinRootsEmptyRootsAllowsAll(): void
    {
        $this->assertTrue(RepositoryPath::isWithinRoots('/anything', []));
    }

    // === firstDisallowedBackupPath() / localRepoAllowed() ===

    /** Все пути внутри корней → null. */
    public function testFirstDisallowedBackupPathReturnsNullWhenAllAllowed(): void
    {
        $this->assertNull(RepositoryPath::firstDisallowedBackupPath(['/sources/a', '/sources/b'], ['/sources']));
    }

    /** Возвращает первый путь вне корней. */
    public function testFirstDisallowedBackupPathReturnsFirstOutside(): void
    {
        $this->assertSame('/etc/passwd', RepositoryPath::firstDisallowedBackupPath(['/sources/a', '/etc/passwd'], ['/sources']));
    }

    /** Пустой список корней = всё разрешено. */
    public function testFirstDisallowedBackupPathEmptyRoots(): void
    {
        $this->assertNull(RepositoryPath::firstDisallowedBackupPath(['/anything'], []));
    }

    /** localRepoAllowed. */
    public function testLocalRepoAllowed(): void
    {
        $this->assertTrue(RepositoryPath::localRepoAllowed('/backups/repo', ['/backups']));
        $this->assertFalse(RepositoryPath::localRepoAllowed('/other/repo', ['/backups']));
        $this->assertTrue(RepositoryPath::localRepoAllowed('/other/repo', []));
    }
}
