<?php

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
        $env = $this->ensureHome($env);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $mergedEnv = array_merge($_ENV, $_SERVER, $env);
        // Filter out non-string values to avoid proc_open warnings
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
     * Используется для restic backup.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    public function runStream(array $command, array $env = []): void
    {
        $env = $this->ensureHome($env);

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
     * Гарантирует наличие HOME в окружении.
     * Без HOME restic не может создать кеш — падают ls, find и др.
     *
     * @param array<string, string> $env
     * @return array<string, string>
     */
    private function ensureHome(array $env): array
    {
        if (!isset($env['HOME'])) {
            $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '/tmp');
            $env['HOME'] = $home;
        }
        return $env;
    }
}
