<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Restic;

class CommandRunner
{
    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(array $command, array $env = [], ?string $stdin = null): array
    {
        $env = $this->ensureEnv($env);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $mergedEnv = array_merge($_ENV, $_SERVER, $env);
        $filteredEnv = [];
        foreach ($mergedEnv as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $filteredEnv[$key] = (string) $value;
            }
        }

        $process = proc_open($command, $descriptorSpec, $pipes, null, $filteredEnv);

        if (!is_resource($process)) {
            return [
                'exitCode' => -1,
                'stdout' => '',
                'stderr' => 'Failed to start process',
            ];
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout !== false ? $stdout : '',
            'stderr' => $stderr !== false ? $stderr : '',
        ];
    }

    /**
     * Запускает команду и стримит stdout в браузер в реальном времени.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    public function runStream(array $command, array $env = []): void
    {
        $env = $this->ensureEnv($env);

        set_time_limit(0);

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $mergedEnv = array_merge($_ENV, $_SERVER, $env);
        $filteredEnv = [];
        foreach ($mergedEnv as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $filteredEnv[$key] = (string) $value;
            }
        }

        $process = proc_open($command, $descriptorSpec, $pipes, null, $filteredEnv);

        if (!is_resource($process)) {
            echo "Failed to start process\n";
            return;
        }

        fclose($pipes[0]);

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                echo $chunk;
                flush();
            }
        }
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            \App\Core\App::log('runStream failed (exit ' . $exitCode . '): ' . ($stderr !== false ? $stderr : ''), 0);
        }
    }

    /**
     * Запускает команду и стримит stdout как бинарный файл для скачивания.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    public function runStreamWithHeaders(array $command, array $env, string $contentType, string $filename): void
    {
        $env = $this->ensureEnv($env);

        set_time_limit(0);

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $mergedEnv = array_merge($_ENV, $_SERVER, $env);
        $filteredEnv = [];
        foreach ($mergedEnv as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $filteredEnv[$key] = (string) $value;
            }
        }

        $process = proc_open($command, $descriptorSpec, $pipes, null, $filteredEnv);

        if (!is_resource($process)) {
            echo "Failed to start process\n";
            return;
        }

        fclose($pipes[0]);

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                echo $chunk;
                flush();
            }
        }
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            \App\Core\App::log('runStreamWithHeaders failed (exit ' . $exitCode . '): ' . ($stderr !== false ? $stderr : ''), 0);
        }
    }

    /**
     * Гарантирует переменные окружения для restic в Docker-контейнере.
     *
     * HOME — для .cache/restic (restic падает без HOME).
     * RESTIC_CACHE_DIR — tmp_dir приложения, если доступен для записи.
     * Иначе restic использует HOME/.cache/restic (предупреждение в stderr,
     * но работает).
     *
     * @param array<string, string> $env
     * @return array<string, string>
     */
    private function ensureEnv(array $env): array
    {
        if (!isset($env['HOME'])) {
            $env['HOME'] = '/tmp';
        }

        if (!isset($env['RESTIC_CACHE_DIR'])) {
            $settings = \App\Core\App::configStorage()->loadSettings();
            $tmpDir = rtrim($settings['tmp_dir'] ?? '/tmp', '/');
            $cacheDir = $tmpDir . '/restic-cache';

            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                $env['RESTIC_CACHE_DIR'] = $cacheDir;
            } elseif (@mkdir($cacheDir, 0777, true) || is_dir($cacheDir)) {
                $env['RESTIC_CACHE_DIR'] = $cacheDir;
            }
        }

        return $env;
    }
}
