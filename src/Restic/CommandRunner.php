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
}
