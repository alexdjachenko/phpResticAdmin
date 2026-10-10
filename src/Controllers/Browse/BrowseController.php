<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Browse;

use App\Controllers\BaseController;
use App\Core\App;
use App\Restic\ResticCommandBuilder;

class BrowseController extends BaseController
{
    /**
     * GET /browse — дерево файлов снепшота.
     */
    public function tree(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $request = $this->request();
        $repoId = (string) $request->get('repo', '');
        $snapId = (string) $request->get('snapshot', '');
        $path = (string) $request->get('path', '/');

        if ($repoId === '' || $snapId === '') {
            App::response()->redirect('/snapshots');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $command = ResticCommandBuilder::buildCommand(['ls', '--json', $snapId, $path], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);

        $result = App::runner()->run($command, $env);

        $entries = [];
        if ($result['exitCode'] === 0) {
            $lines = explode("\n", $result['stdout']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            }
        } else {
            App::log('restic ls failed for snapshot ' . $snapId . ' path ' . $path . ': ' . $result['stderr'], 0);
        }

        if ($result['exitCode'] === 0 && empty($entries)) {
            App::log('restic ls empty for snapshot ' . $snapId . ' path ' . $path . ' (stdout: ' . substr($result['stdout'], 0, 200) . ')', 1);
        }

        // Разделяем на папки и файлы, фильтруем мусор
        $normalizedPath = '/' . ltrim($path, '/');
        $dirs = [];
        $files = [];
        foreach ($entries as $entry) {
            $name = $entry['name'] ?? '';
            $entryPath = $entry['path'] ?? '';

            if ($entry === null || $name === '' || $name === '.' || $name === '..') {
                continue;
            }

            // restic ls возвращает сам узел директории среди её детей — фильтруем
            if ($entryPath === $normalizedPath) {
                continue;
            }

            if (($entry['type'] ?? '') === 'dir') {
                $dirs[] = $entry;
            } else {
                $files[] = $entry;
            }
        }

        usort($dirs, function (array $a, array $b): int {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
        usort($files, function (array $a, array $b): int {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        $breadcrumbs = $this->buildBreadcrumbs($repo, $snapId, $path);

        $this->render('browse/tree.php', [
            'repo' => $repo,
            'snapId' => $snapId,
            'currentPath' => $path,
            'dirs' => $dirs,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * @param array<string, mixed> $repo
     * @return array<int, array{label: string, url: string|null}>
     */
    private function buildBreadcrumbs(array $repo, string $snapId, string $path): array
    {
        $crumbs = [];

        $crumbs[] = [
            'label' => $repo['name'] ?? $repo['id'] ?? 'Repo',
            'url' => '/repositories/detail?repo=' . urlencode($repo['id'] ?? ''),
        ];

        $shortId = substr($snapId, 0, 8);
        $crumbs[] = [
            'label' => $shortId,
            'url' => '/snapshots?repo=' . urlencode($repo['id'] ?? ''),
        ];

        $path = '/' . ltrim($path, '/');
        $segments = array_values(array_filter(explode('/', $path), function (string $s): bool { return $s !== ''; }));
        $accumulatedPath = '';
        foreach ($segments as $segment) {
            $accumulatedPath .= '/' . $segment;
            $crumbs[] = [
                'label' => $segment,
                'url' => '/browse?repo=' . urlencode($repo['id'] ?? '') . '&snapshot=' . urlencode($snapId) . '&path=' . urlencode($accumulatedPath),
            ];
        }

        return $crumbs;
    }
}
