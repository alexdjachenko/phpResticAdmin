<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Storage;

use App\Storage\RepositoryStorage;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест RepositoryStorage (CRUD репозиториев в YAML и сессии).
 *
 * Цель: проверить парсинг YAML, автосоздание файла, сохранение/загрузку
 *       по категориям, удаление, перемещение, session-репозитории, update.
 *
 * Сценарий:
 *   - loadAll: парсинг YAML, автосоздание файла при отсутствии, пустой/невалидный YAML.
 *   - save/load по категориям (public, private).
 *   - delete: удаление из категории.
 *   - move: перемещение между категориями.
 *   - session: загрузка, удаление, сохранение.
 *   - update: изменение полей (имя, тип, путь, backup_paths).
 *
 * Критерий успеха: все assert проходят.
 */
class RepositoryStorageTest extends TestCase
{
    /** @var string Временная директория */
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_repo_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /** Парсинг валидного YAML с двумя репозиториями. */
    public function testLoadAllParsesYaml(): void
    {
        $yaml = <<<YAML
repositories:
    - id: "abc123"
      name: "Test Backup"
      type: "local"
      path: "/backups/test"
      password: null
    - id: "def456"
      name: "Remote Backup"
      type: "sftp"
      path: "sftp:host:/backup"
      password: "secret"
YAML;
        file_put_contents($this->tmpDir . '/repositories.yaml', $yaml);

        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll('testuser');

        $this->assertCount(2, $repos);
        $this->assertSame('abc123', $repos[0]['id']);
        $this->assertSame('Test Backup', $repos[0]['name']);
        $this->assertSame('local', $repos[0]['type']);
        $this->assertNull($repos[0]['password']);
        $this->assertSame('def456', $repos[1]['id']);
        $this->assertSame('secret', $repos[1]['password']);
    }

    /** Отсутствующий файл → автосоздание с шаблоном, возврат пустого массива. */
    public function testLoadAllReturnsEmptyArrayWhenFileMissing(): void
    {
        $path = $this->tmpDir . '/nonexistent.yaml';
        $storage = new RepositoryStorage($path);
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);

        // Файл должен быть автосоздан с шаблонным комментарием
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString('# repositories:', $content);
        $this->assertStringContainsString('#   - id:', $content);
    }

    /** Пустой YAML → пустой массив. */
    public function testLoadAllReturnsEmptyArrayForEmptyYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    /** Не-массив в YAML (скаляр) → пустой массив (защита). */
    public function testLoadAllReturnsEmptyArrayForNonArrayYaml(): void
    {
        file_put_contents($this->tmpDir . '/repositories.yaml', '42');
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');
        $repos = $storage->loadAll('testuser');

        $this->assertIsArray($repos);
        $this->assertEmpty($repos);
    }

    /** Сохранение в public и private, загрузка всех через loadAll. */
    public function testSaveAndLoadByCategory(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        // Сохраняем public-репо
        $repo = [
            'id' => 'pub1',
            'name' => 'Public Repo',
            'type' => 'local',
            'path' => '/backups/pub',
            'password' => null,
        ];
        $storage->save('public', $repo, 'testuser');

        // Сохраняем private-репо
        $repo2 = [
            'id' => 'priv1',
            'name' => 'Private Repo',
            'type' => 'local',
            'path' => '/backups/priv',
            'password' => null,
        ];
        $storage->save('private', $repo2, 'testuser');

        // loadAll должен вернуть оба
        $all = $storage->loadAll('testuser');
        $this->assertCount(2, $all);

        // Проверяем категории
        $categories = [];
        foreach ($all as $r) {
            $categories[] = $r['category'];
        }
        $this->assertContains('public', $categories);
        $this->assertContains('private', $categories);
    }

    /** Удаление репозитория из категории. */
    public function testDeleteRemovesFromCorrectStorage(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'del1', 'name' => 'To Delete', 'type' => 'local', 'path' => '/tmp/del', 'password' => null], 'testuser');
        $storage->save('public', ['id' => 'keep1', 'name' => 'Keep', 'type' => 'local', 'path' => '/tmp/keep', 'password' => null], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(2, $all);

        // Удаляем один
        $storage->delete('public', 'del1', 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('keep1', $all[0]['id']);
    }

    /** Перемещение репозитория между категориями. */
    public function testMoveTransfersBetweenCategories(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'move1', 'name' => 'Move Me', 'type' => 'local', 'path' => '/tmp/move', 'password' => null], 'testuser');

        // Перемещаем public → private
        $storage->move('move1', 'public', 'private', 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('private', $all[0]['category']);
        $this->assertSame('move1', $all[0]['id']);
        $this->assertSame('Move Me', $all[0]['name']);
    }

    /** Загрузка session-репозиториев из $_SESSION. */
    public function testSessionReposAreLoaded(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $_SESSION['session_repos'] = [
            ['id' => 'sess1', 'name' => 'Session Repo', 'type' => 'local', 'path' => '/tmp/sess', 'password' => null],
        ];

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('session', $all[0]['category']);
        $this->assertSame('sess1', $all[0]['id']);

        unset($_SESSION['session_repos']);
    }

    /** Удаление session-репозитория. */
    public function testSessionReposDeleted(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $_SESSION['session_repos'] = [
            ['id' => 'sess1', 'name' => 'S1', 'type' => 'local', 'path' => '/tmp/s1', 'password' => null],
            ['id' => 'sess2', 'name' => 'S2', 'type' => 'local', 'path' => '/tmp/s2', 'password' => null],
        ];

        $storage->delete('session', 'sess1', 'testuser');

        $repos = $storage->loadSession();
        $this->assertCount(1, $repos);
        $this->assertSame('sess2', $repos[0]['id']);

        unset($_SESSION['session_repos']);
    }

    /** Сохранение session-репозитория. */
    public function testSaveSessionRepo(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('session', ['id' => 'newsess', 'name' => 'New', 'type' => 'local', 'path' => '/tmp/new', 'password' => null], 'testuser');

        $repos = $storage->loadSession();
        $this->assertCount(1, $repos);
        $this->assertSame('newsess', $repos[0]['id']);

        unset($_SESSION['session_repos']);
    }

    /** update сохраняет id и изменяет указанные поля. */
    public function testUpdatePreservesId(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'upd1', 'name' => 'Original', 'type' => 'local', 'path' => '/tmp/orig', 'password' => null], 'testuser');

        $storage->update('public', 'upd1', ['name' => 'Updated Name'], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(1, $all);
        $this->assertSame('upd1', $all[0]['id']);
        $this->assertSame('Updated Name', $all[0]['name']);
        $this->assertSame('local', $all[0]['type']);
        $this->assertSame('/tmp/orig', $all[0]['path']);
    }

    /** update изменяет name, type, path. */
    public function testUpdateChangesEditableFields(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'upd2', 'name' => 'Original', 'type' => 'local', 'path' => '/tmp/orig', 'password' => null], 'testuser');

        $storage->update('public', 'upd2', ['name' => 'New Name', 'type' => 'sftp', 'path' => '/new/path'], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertSame('New Name', $all[0]['name']);
        $this->assertSame('sftp', $all[0]['type']);
        $this->assertSame('/new/path', $all[0]['path']);
    }

    /** update backup_paths: добавление и удаление. */
    public function testUpdateBackupPaths(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'upd3', 'name' => 'BP Repo', 'type' => 'local', 'path' => '/tmp/bp', 'password' => null], 'testuser');

        // Добавляем backup_paths
        $storage->update('public', 'upd3', ['backup_paths' => ['/home', '/etc']], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertSame(['/home', '/etc'], $all[0]['backup_paths']);

        // Удаляем backup_paths (null)
        $storage->update('public', 'upd3', ['backup_paths' => null], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertNull($all[0]['backup_paths'] ?? null);
        }

        /** update сохраняет password и env (включая AWS_SECRET_ACCESS_KEY), когда они не переданы. */
        public function testUpdatePreservesPasswordAndEnvWhenOmitted(): void
        {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', [
            'id' => 'upd-env',
            'name' => 'S3 Repo',
            'type' => 's3',
            's3_bucket' => 'my-bucket',
            'password' => 'secret-pass',
            'env' => [
                'AWS_ACCESS_KEY_ID' => 'AKIA123',
                'AWS_SECRET_ACCESS_KEY' => 's3-secret',
                'AWS_ENDPOINT' => 'https://s3.example.com',
            ],
        ], 'testuser');

        // Имитация edit(): изменяется только имя, password и env не передаются.
        $storage->update('public', 'upd-env', ['name' => 'Renamed'], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertSame('secret-pass', $all[0]['password']);
        $this->assertSame('AKIA123', $all[0]['env']['AWS_ACCESS_KEY_ID'] ?? null);
        $this->assertSame('s3-secret', $all[0]['env']['AWS_SECRET_ACCESS_KEY'] ?? null);
        }

        /** Сохранение типоспецифичных полей расположения (local_path/s3_bucket/sftp_path/rest_url). */
    public function testSaveWithTypeSpecificLocationFields(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 'local-1', 'name' => 'Local', 'type' => 'local', 'local_path' => '/backups/repo', 'password' => null], 'testuser');
        $storage->save('public', ['id' => 's3-1', 'name' => 'S3', 'type' => 's3', 's3_bucket' => 'my-bucket', 'password' => null], 'testuser');
        $storage->save('public', ['id' => 'sftp-1', 'name' => 'SFTP', 'type' => 'sftp', 'sftp_path' => 'user@host:/repo', 'password' => null], 'testuser');
        $storage->save('public', ['id' => 'rest-1', 'name' => 'REST', 'type' => 'rest', 'rest_url' => 'http://host:8000/', 'password' => null], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertCount(4, $all);

        $byId = [];
        foreach ($all as $r) {
            $byId[$r['id']] = $r;
        }
        $this->assertSame('/backups/repo', $byId['local-1']['local_path']);
        $this->assertSame('my-bucket', $byId['s3-1']['s3_bucket']);
        $this->assertSame('user@host:/repo', $byId['sftp-1']['sftp_path']);
        $this->assertSame('http://host:8000/', $byId['rest-1']['rest_url']);
    }

    /** update очищает старое location-поле при смене типа. */
    public function testUpdateClearsOldLocationFieldOnTypeChange(): void
    {
        $storage = new RepositoryStorage($this->tmpDir . '/repositories.yaml');

        $storage->save('public', ['id' => 't1', 'name' => 'Repo', 'type' => 'local', 'local_path' => '/backups/repo', 'password' => null], 'testuser');

        $storage->update('public', 't1', [
            'type' => 's3',
            'local_path' => null,
            's3_bucket' => 'my-bucket',
            'sftp_path' => null,
            'rest_url' => null,
        ], 'testuser');

        $all = $storage->loadAll('testuser');
        $this->assertSame('s3', $all[0]['type']);
        $this->assertSame('my-bucket', $all[0]['s3_bucket']);
        $this->assertNull($all[0]['local_path'] ?? null);
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
