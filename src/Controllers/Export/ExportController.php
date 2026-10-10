<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Export;

use App\Controllers\BaseController;
use App\Core\App;
use App\Restic\ResticCommandBuilder;

class ExportController extends BaseController
{
    /**
     * GET /download — скачивание отдельного файла из снепшота.
     */
    public function file(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $request = $this->request();
        $repoId = (string) $request->get('repo', '');
        $snapId = (string) $request->get('snapshot', '');
        $path = (string) $request->get('path', '');

        if ($repoId === '' || $snapId === '' || $path === '') {
            App::response()->error(400, 'Missing parameters');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
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
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $request = $this->request();
        $repoId = (string) $request->get('repo', '');
        $snapId = (string) $request->get('snapshot', '');

        if ($repoId === '' || $snapId === '') {
            App::response()->error(400, 'Missing parameters');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
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
