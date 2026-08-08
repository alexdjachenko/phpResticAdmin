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
        $command = ['restic', 'snapshots', '--json', '--repo', $repository['path']];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $decoded = json_decode($result['stdout'], true);

        if (!is_array($decoded)) {
            return [];
        }

        // В старых версиях restic summary.total_size отсутствует в snapshots --json.
        // Пробуем получить размеры через restic stats (один вызов для всех снепшотов).
        $hasSummary = false;
        foreach ($decoded as $snap) {
            if (isset($snap['summary']['total_size'])) {
                $hasSummary = true;
                break;
            }
        }

        if (!$hasSummary && !empty($decoded)) {
            $decoded = $this->enrichWithSizes($repository, $decoded, $env);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $repository
     * @param array<int, array<string, mixed>> $snapshots
     * @param array<string, string> $env
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithSizes(array $repository, array $snapshots, array $env): array
    {
        // Собираем ID всех снепшотов
        $ids = [];
        foreach ($snapshots as $snap) {
            if (!empty($snap['id'])) {
                $ids[] = $snap['id'];
            }
        }

        if (empty($ids)) {
            return $snapshots;
        }

        // restic stats --json --mode raw-data <id1> <id2> ...
        $command = array_merge(
            ['restic', 'stats', '--json', '--mode', 'raw-data', '--repo', $repository['path']],
            $ids
        );

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

        $result = $this->runner->run($command, $env);

        if ($result['exitCode'] !== 0) {
            return $snapshots;
        }

        // stats --json для нескольких ID возвращает массив объектов
        $statsEntries = json_decode($result['stdout'], true);
        if (!is_array($statsEntries)) {
            return $snapshots;
        }

        // Индексируем размеры по snapshot_id
        $sizeMap = [];
        foreach ($statsEntries as $entry) {
            $sid = $entry['snapshot_id'] ?? $entry['id'] ?? null;
            $ts = $entry['total_size'] ?? null;
            if ($sid !== null && $ts !== null) {
                $sizeMap[$sid] = (int) $ts;
            }
        }

        // Проставляем size в снапшоты
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
        $command = ['restic', 'tag', '--repo', $repository['path'], $operation, $tag, $snapId];

        $env = $repository['env'] ?? [];

        if (!empty($repository['password'])) {
            $env['RESTIC_PASSWORD'] = $repository['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

        $result = $this->runner->run($command, $env);

        return [
            'ok' => $result['exitCode'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
}
