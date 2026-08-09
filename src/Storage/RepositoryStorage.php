<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Storage;

use App\Core\App;
use Symfony\Component\Yaml\Yaml;

class RepositoryStorage
{
    private string $dataFile;
    private string $dataDir;

    public function __construct(?string $dataFile = null)
    {
        $this->dataFile = $dataFile ?? dirname(__DIR__, 2) . '/data/data/repositories.yaml';
        $this->dataDir = dirname($this->dataFile);
    }

    /**
     * Загружает public-репозитории из общего YAML-файла.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadPublic(): array
    {
        return $this->loadYaml($this->dataFile);
    }

    /**
     * Загружает private-репозитории пользователя из его YAML-файла.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadPrivate(string $username): array
    {
        $file = $this->dataDir . '/repositories_' . $username . '.yaml';
        return $this->loadYaml($file);
    }

    /**
     * Загружает session-репозитории.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadSession(): array
    {
        return $_SESSION['session_repos'] ?? [];
    }

    /**
     * Загружает все репозитории для пользователя, добавляя поле category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadAll(string $username): array
    {
        $repos = [];

        foreach ($this->loadPublic() as $repo) {
            $repo['category'] = 'public';
            $repos[] = $repo;
        }

        foreach ($this->loadPrivate($username) as $repo) {
            $repo['category'] = 'private';
            $repos[] = $repo;
        }

        foreach ($this->loadSession() as $repo) {
            $repo['category'] = 'session';
            $repos[] = $repo;
        }

        return $repos;
    }

    /**
     * Сохраняет репозиторий в указанную категорию.
     *
     * @param array<string, mixed> $repository
     */
    public function save(string $category, array $repository, ?string $username): void
    {
        // Убираем category из данных перед сохранением
        unset($repository['category']);

        switch ($category) {
            case 'public':
                $this->saveYaml($this->dataFile, $repository);
                break;
            case 'private':
                if ($username === null) {
                    throw new \RuntimeException('Username required for private repositories');
                }
                $file = $this->dataDir . '/repositories_' . $username . '.yaml';
                $this->saveYaml($file, $repository);
                break;
            case 'session':
                if (!isset($_SESSION['session_repos'])) {
                    $_SESSION['session_repos'] = [];
                }
                $_SESSION['session_repos'][] = $repository;
                break;
            default:
                throw new \InvalidArgumentException('Unknown category: ' . $category);
        }
    }

    /**
     * Удаляет репозиторий по ID из указанной категории.
     */
    public function delete(string $category, string $id, ?string $username): void
    {
        switch ($category) {
            case 'public':
                $this->deleteFromYaml($this->dataFile, $id);
                break;
            case 'private':
                if ($username === null) {
                    throw new \RuntimeException('Username required for private repositories');
                }
                $file = $this->dataDir . '/repositories_' . $username . '.yaml';
                $this->deleteFromYaml($file, $id);
                break;
            case 'session':
                if (isset($_SESSION['session_repos'])) {
                    $_SESSION['session_repos'] = array_values(
                        array_filter($_SESSION['session_repos'], function (array $repo) use ($id): bool {
                            return ($repo['id'] ?? '') !== $id;
                        })
                    );
                }
                break;
            default:
                throw new \InvalidArgumentException('Unknown category: ' . $category);
        }
    }

    /**
     * Обновляет репозиторий по ID в указанной категории.
     * Сохраняет неизменяемые поля: id, added_at, category.
     *
     * @param array<string, mixed> $newData
     */
    public function update(string $category, string $id, array $newData, ?string $username): void
    {
        switch ($category) {
            case 'public':
                $repos = $this->loadYaml($this->dataFile);
                $updated = false;
                foreach ($repos as &$repo) {
                    if (($repo['id'] ?? '') === $id) {
                        $this->applyUpdate($repo, $newData);
                        $updated = true;
                        break;
                    }
                }
                unset($repo);
                if (!$updated) {
                    throw new \RuntimeException('Repository not found: ' . $id);
                }
                $this->writeYaml($this->dataFile, $repos);
                break;

            case 'private':
                if ($username === null) {
                    throw new \RuntimeException('Username required for private repositories');
                }
                $file = $this->dataDir . '/repositories_' . $username . '.yaml';
                $repos = $this->loadYaml($file);
                $updated = false;
                foreach ($repos as &$repo) {
                    if (($repo['id'] ?? '') === $id) {
                        $this->applyUpdate($repo, $newData);
                        $updated = true;
                        break;
                    }
                }
                unset($repo);
                if (!$updated) {
                    throw new \RuntimeException('Repository not found: ' . $id);
                }
                $this->writeYaml($file, $repos);
                break;

            case 'session':
                if (!isset($_SESSION['session_repos'])) {
                    throw new \RuntimeException('Repository not found: ' . $id);
                }
                $updated = false;
                foreach ($_SESSION['session_repos'] as &$repo) {
                    if (($repo['id'] ?? '') === $id) {
                        $this->applyUpdate($repo, $newData);
                        $updated = true;
                        break;
                    }
                }
                unset($repo);
                if (!$updated) {
                    throw new \RuntimeException('Repository not found: ' . $id);
                }
                break;

            default:
                throw new \InvalidArgumentException('Unknown category: ' . $category);
        }
    }

    /**
     * Применяет новые данные к репозиторию, сохраняя неизменяемые поля.
     *
     * @param array<string, mixed> $repo
     * @param array<string, mixed> $newData
     */
    private function applyUpdate(array &$repo, array $newData): void
    {
        $editable = ['name', 'type', 'path', 'password', 'backup_paths', 'env'];
        foreach ($editable as $field) {
            if (array_key_exists($field, $newData)) {
                $repo[$field] = $newData[$field];
            }
        }
    }

    /**
     * Переносит репозиторий из одной категории в другую.
     */
    public function move(string $id, string $fromCategory, string $toCategory, ?string $username): void
    {
        // Найти репозиторий в исходной категории
        $repo = null;
        switch ($fromCategory) {
            case 'public':
                $repos = $this->loadYaml($this->dataFile);
                break;
            case 'private':
                if ($username === null) {
                    throw new \RuntimeException('Username required for private repositories');
                }
                $file = $this->dataDir . '/repositories_' . $username . '.yaml';
                $repos = $this->loadYaml($file);
                break;
            case 'session':
                $repos = $_SESSION['session_repos'] ?? [];
                break;
            default:
                throw new \InvalidArgumentException('Unknown category: ' . $fromCategory);
        }

        foreach ($repos as $r) {
            if (($r['id'] ?? '') === $id) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            throw new \RuntimeException('Repository not found: ' . $id);
        }

        // Удалить из исходной категории
        $this->delete($fromCategory, $id, $username);

        // Добавить в целевую категорию
        $this->save($toCategory, $repo, $username);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadYaml(string $file): array
    {
        if (!file_exists($file)) {
            // Для public-файла создаём пустой с шаблоном
            if ($file === $this->dataFile) {
                App::log('repositories.yaml not found, creating empty file', 0);
                $this->createEmpty();
                return [];
            }
            return [];
        }

        $data = Yaml::parseFile($file);

        if (!is_array($data)) {
            return [];
        }

        $repositories = $data['repositories'] ?? $data;

        if (!is_array($repositories)) {
            return [];
        }

        return array_values($repositories);
    }

    /**
     * @param array<string, mixed> $repository
     */
    private function saveYaml(string $file, array $repository): void
    {
        $repos = file_exists($file) ? $this->loadYaml($file) : [];
        $repos[] = $repository;

        $this->writeYaml($file, $repos);
    }

    private function deleteFromYaml(string $file, string $id): void
    {
        if (!file_exists($file)) {
            return;
        }

        $repos = $this->loadYaml($file);
        $repos = array_values(array_filter($repos, function (array $repo) use ($id): bool {
            return ($repo['id'] ?? '') !== $id;
        }));

        $this->writeYaml($file, $repos);
    }

    /**
     * @param array<int, array<string, mixed>> $repos
     */
    private function writeYaml(string $file, array $repos): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $data = ['repositories' => $repos];
        file_put_contents($file, Yaml::dump($data, 4, 2));
    }

    private function createEmpty(): void
    {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $yaml = "# repositories:\n#   - id: \"a1b2c3d4e5f6g7h8\"\n#     name: \"Example Backup\"\n#     type: \"local\"\n#     path: \"/backups/example\"\n#     password: null\n";
        file_put_contents($this->dataFile, $yaml);
    }
}
