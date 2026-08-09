<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class BrowseController
{
    /**
     * GET /browse — дерево файлов снепшота.
     */
    public function tree(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $request->get('repo', '');
        $snapId = $request->get('snapshot', '');
        $path = $request->get('path', '/');

        if ($repoId === '' || $snapId === '') {
            App::response()->redirect('/snapshots');
            return;
        }

        $repositories = App::repoStorage()->loadAll($user);
        $repo = null;
        foreach ($repositories as $r) {
            if (($r['id'] ?? '') === $repoId) {
                $repo = $r;
                break;
            }
        }

        if ($repo === null) {
            App::response()->error(404, __('flash.not_found'));
            return;
        }

        $category = $repo['category'] ?? 'public';
        if (!$auth->canUse($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $command = ['restic', 'ls', '--json', '--repo', $repo['path'], $snapId, $path];

        $env = $repo['env'] ?? [];

        if (!empty($repo['password'])) {
            $env['RESTIC_PASSWORD'] = $repo['password'];
        } else {
            $command[] = '--insecure-no-password';
        }

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

        echo App::response()->render('browse/tree.php', [
            'repo' => $repo,
            'snapId' => $snapId,
            'currentPath' => $path,
            'dirs' => $dirs,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
            'isLoggedIn' => $auth->isLoggedIn(),
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
