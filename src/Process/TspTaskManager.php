<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

/**
 * Менеджер фоновых задач поверх TspClient.
 *
 * Метка задачи = `<username>#<hex>` (например, `alice#3f2a9c1b`). Метка
 * используется как идентификатор без отдельной БД. Символ `#` в логинах
 * запрещён, чтобы префикс `<username>#` однозначно отделял владельца.
 *
 * Обычный пользователь видит только свои задачи (label начинается с
 * `<его_username>#`); пользователь с `can_manage_processes` видит все.
 */
class TspTaskManager
{
    private TspClient $tsp;

    public function __construct(TspClient $tsp)
    {
        $this->tsp = $tsp;
    }

    /**
     * Ставит команду в очередь от имени пользователя.
     *
     * $separateStderr = true включает `tsp -E`: stdout и stderr задачи пишутся
     * в разные файлы. Нужно для JSON-задач, чтобы вывод stderr не ломал JSON.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{label: string, id: int}
     */
    public function start(string $username, array $command, array $env = [], bool $separateStderr = false): array
    {
        $label = $username . '#' . bin2hex(random_bytes(8));
        return $this->tsp->enqueue($label, $command, $env, $separateStderr);
    }

    /**
     * Задачи, видимые пользователю.
     *
     * @return array<int, array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}>
     */
    public function listForUser(string $username, bool $privileged): array
    {
        $jobs = $this->tsp->list();

        if ($privileged) {
            return $jobs;
        }

        return array_values(array_filter($jobs, function (array $job) use ($username): bool {
            $label = $job['label'] ?? '';
            return $label !== '' && str_starts_with($label, $username . '#');
        }));
    }

    /**
     * @return array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}|null
     */
    public function findByLabel(string $label): ?array
    {
        foreach ($this->tsp->list() as $job) {
            if (($job['label'] ?? '') === $label) {
                return $job;
            }
        }
        return null;
    }

    /**
     * Статус задачи: state, exitCode (если известен) и хвост вывода.
     *
     * @return array{label: string, id: int, state: string, exitCode: ?int, output: string}|null
     */
    public function status(string $username, string $label, bool $privileged): ?array
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            return null;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            return null;
        }

        $id = $job['id'];
        $state = $this->tsp->state($id);

        $exitCode = null;
        if ($state === 'finished') {
            $exitCode = $this->tsp->wait($id);
        }

        return [
            'label' => $label,
            'id' => $id,
            'state' => $state,
            'exitCode' => $exitCode,
            'output' => $this->readTail($id),
        ];
    }

    /**
     * Полный вывод завершённой задачи (tsp -c), блокируется до завершения.
     */
    public function fullOutput(string $username, string $label, bool $privileged): ?string
    {
        $result = $this->catResult($username, $label, $privileged);
        return $result !== null ? $result['output'] : null;
    }

    /**
     * Полный вывод завершённой задачи вместе с кодом возврата.
     *
     * @return array{exitCode: int, output: string}|null
     */
    public function catResult(string $username, string $label, bool $privileged): ?array
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            return null;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            return null;
        }

        $result = $this->tsp->catRaw($job['id']);

        return [
            'exitCode' => $result['exitCode'],
            'output' => $result['stdout'],
        ];
    }

    /**
     * Завершена ли задача (state === finished).
     */
    public function isFinished(string $username, string $label, bool $privileged): bool
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            return false;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            return false;
        }

        return $this->tsp->state($job['id']) === 'finished';
    }

    /**
     * Проверка доступа: своя задача — всегда; чужая — только привилегированный.
     */
    public function assertAccess(string $username, string $label, bool $privileged): bool
    {
        if (!$this->isValidLabel($label)) {
            return false;
        }

        if ($privileged) {
            return true;
        }

        return str_starts_with($label, $username . '#');
    }

    /**
     * Стримит вывод задачи в браузер по мере появления.
     *
     * @param string|null $prefix необязательная строка, выводимая до вывода задачи
     */
    public function streamOutput(string $username, string $label, bool $privileged, ?string $prefix = null): void
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            http_response_code(403);
            echo "Access denied\n";
            exit;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            http_response_code(404);
            echo "Task not found\n";
            exit;
        }

        $id = $job['id'];

        set_time_limit(0);

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');

        if ($prefix !== null && $prefix !== '') {
            echo $prefix . "\n\n";
            flush();
        }

        $pos = 0;

        while (true) {
            $outputFile = $this->tsp->outputFile($id);

            if ($outputFile !== null && file_exists($outputFile)) {
                $handle = @fopen($outputFile, 'rb');
                if ($handle !== false) {
                    fseek($handle, $pos);
                    while (!feof($handle)) {
                        $chunk = fread($handle, 8192);
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        echo $chunk;
                        $pos += strlen($chunk);
                        flush();
                    }
                    fclose($handle);
                }
            }

            $state = $this->tsp->state($id);
            if (in_array($state, ['finished', 'skipped', 'unknown'], true)) {
                break;
            }

            usleep(200000);
        }

        exit;
    }

    /**
     * Валидация формата метки: `^[^#]+#[0-9a-f]+$`.
     */
    public function isValidLabel(string $label): bool
    {
        return preg_match('/^[^#]+#[0-9a-f]+$/', $label) === 1;
    }

    /**
     * Читает хвост output-файла задачи без блокировки (для status()).
     */
    private function readTail(int $id, int $lines = 50): string
    {
        $outputFile = $this->tsp->outputFile($id);
        if ($outputFile === null || !file_exists($outputFile)) {
            return '';
        }

        $data = @file_get_contents($outputFile);
        if ($data === false || $data === '') {
            return '';
        }

        $chunks = explode("\n", $data);
        $tail = array_slice($chunks, -$lines);

        return implode("\n", $tail);
    }
}
