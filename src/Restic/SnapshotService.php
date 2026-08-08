<?php

namespace App\Restic;

class SnapshotService
{
    private CommandRunner $runner;

    public function __construct(CommandRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<int, array<string, mixed>>
     */
    public function listSnapshots(array $repository): array
    {
        $command = $this->buildCommand(['snapshots', '--json'], $repository);
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $decoded = json_decode($result['stdout'], true);

        if (!is_array($decoded)) {
            return [];
        }

        $hasSummary = false;
        foreach ($decoded as $snap) {
            if (isset($snap['summary']['total_size'])) {
                $hasSummary = true;
                break;
            }
        }

        if (!$hasSummary && !empty($decoded)) {
            $decoded = $this->enrichWithSizes($repository, $decoded);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $repository
     * @param array<int, array<string, mixed>> $snapshots
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithSizes(array $repository, array $snapshots): array
    {
        $ids = [];
        foreach ($snapshots as $snap) {
            if (!empty($snap['id'])) {
                $ids[] = $snap['id'];
            }
        }

        if (empty($ids)) {
            return $snapshots;
        }

        $env = $this->buildEnv($repository);

        // Пробуем stats --json (restic >= 0.16)
        $command = $this->buildCommand(['stats', '--json', '--mode', 'raw-data'], $repository);
        $command = array_merge($command, $ids);

        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] === 0) {
            $statsEntries = json_decode($result['stdout'], true);
            if (is_array($statsEntries)) {
                return $this->applySizeMap($snapshots, $this->parseStatsJson($statsEntries));
            }
        }

        // Fallback: stats без --json (restic < 0.16)
        $command = $this->buildCommand(['stats', '--mode', 'raw-data'], $repository);
        $command = array_merge($command, $ids);

        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] === 0) {
            $sizeMap = $this->parseStatsText($result['stdout']);
            if (!empty($sizeMap)) {
                return $this->applySizeMap($snapshots, $sizeMap);
            }
        }

        return $snapshots;
    }

    /**
     * @param array<int, array<string, mixed>> $statsEntries
     * @return array<string, int>
     */
    private function parseStatsJson(array $statsEntries): array
    {
        $map = [];
        foreach ($statsEntries as $entry) {
            $sid = $entry['snapshot_id'] ?? $entry['id'] ?? null;
            $ts = $entry['total_size'] ?? null;
            if ($sid !== null && $ts !== null) {
                $map[$sid] = (int) $ts;
            }
        }
        return $map;
    }

    /**
     * Парсит текстовый вывод restic stats (restic < 0.16).
     * Формат для нескольких ID: секции вида
     *   snapshot <id> of [...] at ...:
     *     ...
     *           Total Size:   <value> <unit>
     *
     * @return array<string, int>
     */
    private function parseStatsText(string $stdout): array
    {
        $map = [];

        // Разбиваем на секции по snapshot <id>
        $blocks = preg_split('/\n(?=snapshot )/', $stdout);
        foreach ($blocks as $block) {
            if (!preg_match('/^snapshot ([a-f0-9]+) /m', $block, $idMatch)) {
                continue;
            }
            $sid = $idMatch[1];

            if (!preg_match('/Total Size:\s+([\d.]+)\s*(\w+)/', $block, $sizeMatch)) {
                continue;
            }

            $value = (float) $sizeMatch[1];
            $unit = $sizeMatch[2];

            $map[$sid] = $this->parseHumanSize($value, $unit);
        }

        return $map;
    }

    /**
     * Переводит человекочитаемый размер в байты.
     */
    private function parseHumanSize(float $value, string $unit): int
    {
        $multipliers = [
            'B'   => 1,
            'KiB' => 1024,
            'MiB' => 1024 * 1024,
            'GiB' => 1024 * 1024 * 1024,
            'TiB' => 1024 * 1024 * 1024 * 1024,
        ];

        $power = $multipliers[$unit] ?? 1;
        return (int) round($value * $power);
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     * @param array<string, int> $sizeMap
     * @return array<int, array<string, mixed>>
     */
    private function applySizeMap(array $snapshots, array $sizeMap): array
    {
        foreach ($snapshots as &$snap) {
            $sid = $snap['id'] ?? '';
            if (isset($sizeMap[$sid])) {
                $snap['summary'] = ['total_size' => $sizeMap[$sid]];
            }
        }
        unset($snap);
        return $snapshots;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<string, mixed>|null
     */
    public function getSnapshot(array $repository, string $snapId): ?array
    {
        $snapshots = $this->listSnapshots($repository);

        foreach ($snapshots as $snap) {
            if (($snap['short_id'] ?? '') === $snapId || ($snap['id'] ?? '') === $snapId) {
                return $snap;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function addTag(array $repository, string $snapId, string $tag): array
    {
        return $this->tagOperation($repository, $snapId, $tag, '--add');
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    public function removeTag(array $repository, string $snapId, string $tag): array
    {
        return $this->tagOperation($repository, $snapId, $tag, '--remove');
    }

    /**
     * @param array<string, mixed> $repository
     * @return array{ok: bool, output: string, error: string}
     */
    private function tagOperation(array $repository, string $snapId, string $tag, string $operation): array
    {
        $command = $this->buildCommand(
            ['tag', '--repo', $repository['path'], $operation, $tag, $snapId],
            $repository
        );
        $env = $this->buildEnv($repository);
        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }

    /**
     * Строит команду restic. --insecure-no-password — глобальный флаг,
     * должен идти ДО подкоманды, иначе restic примет его за snapshot ID.
     *
     * @param array<int, string> $subcommandArgs
     * @param array<string, mixed> $repository
     * @return array<int, string>
     */
    private function buildCommand(array $subcommandArgs, array $repository): array
    {
        $cmd = ['restic'];

        if (empty($repository['password'])) {
            $cmd[] = '--insecure-no-password';
        }

        $cmd[] = '--repo';
        $cmd[] = $repository['path'];

        return array_merge($cmd, $subcommandArgs);
    }

    /**
     * @param array<string, mixed> $repository
     * @return array<string, string>
     */
    private function buildEnv(array $repository): array
    {
        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        }

        return $env;
    }
}
