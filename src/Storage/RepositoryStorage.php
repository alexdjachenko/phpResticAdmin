<?php

namespace App\Storage;

use Symfony\Component\Yaml\Yaml;

class RepositoryStorage
{
    private string $dataFile;

    public function __construct(?string $dataFile = null)
    {
        $this->dataFile = $dataFile ?? dirname(__DIR__, 2) . '/data/data/repositories.yaml';
    }

    /**
     * @return array<int, array{id: string, name: string, type: string, path: string, password: ?string, env?: array<string, string>}>
     */
    public function loadAll(): array
    {
        if (!file_exists($this->dataFile)) {
            return [];
        }

        $data = Yaml::parseFile($this->dataFile);

        if (!is_array($data)) {
            return [];
        }

        $repositories = $data['repositories'] ?? $data;

        if (!is_array($repositories)) {
            return [];
        }

        return array_values($repositories);
    }
}
