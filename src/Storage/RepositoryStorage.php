<?php

namespace App\Storage;

use App\Core\App;
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
            App::log('repositories.yaml not found, creating empty file', 0);
            $this->createEmpty();
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
