<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Restic\ResticCommandBuilder;

class ExportController
{
    /**
     * GET /download — скачивание отдельного файла из снепшота.
     */
    public function file(): void
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
        $path = $request->get('path', '');

        if ($repoId === '' || $snapId === '' || $path === '') {
            App::response()->error(400, 'Missing parameters');
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
        if (!$auth->canUseRead($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $filename = basename($path);
        $mime = $this->getMimeType($filename);

        $command = ResticCommandBuilder::buildCommand(['dump', $snapId, $path], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);

        App::runner()->runStreamWithHeaders($command, $env, $mime, $filename);
    }

    /**
     * GET /export — скачивание целого снепшота как tar-архива.
     */
    public function snapshot(): void
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

        if ($repoId === '' || $snapId === '') {
            App::response()->error(400, 'Missing parameters');
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
        if (!$auth->canUseRead($category)) {
            App::response()->error(403, __('error.forbidden'));
            return;
        }

        $shortId = substr($snapId, 0, 8);
        $filename = 'snapshot-' . $shortId . '.tar';

        $command = ResticCommandBuilder::buildCommand(['dump', $snapId, '/'], $repo);
        $env = ResticCommandBuilder::buildEnv($repo);

        App::runner()->runStreamWithHeaders($command, $env, 'application/x-tar', $filename);
    }

    private function getMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'zip' => 'application/zip',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yaml', 'yml' => 'text/yaml',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
