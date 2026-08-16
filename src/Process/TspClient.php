<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

use App\Restic\CommandRunner;

/**
 * Низкоуровневая обёртка над бинарником `tsp` (task spooler).
 *
 * Каждый экземпляр работает со своей очередью через переменные окружения
 * TMPDIR/TS_SOCKET (и, при необходимости, TS_SLOTS). Все вызовы `tsp`
 * выполняются через CommandRunner без таймаута (timeout 0), потому что
 * часть операций (например, `tsp -w`) может блокироваться до завершения задачи.
 *
 * Символ `#` в label запрещён на уровне TspTaskManager; сюда label приходит
 * уже сформированным.
 */
class TspClient
{
    private CommandRunner $runner;
    private string $binary;
    private string $tmpDir;
    private string $socket;
    private ?string $slots;

    public function __construct(CommandRunner $runner, ?string $tmpDir = null, ?string $socket = null)
    {
        $this->runner = $runner;

        $settings = \App\Core\App::configStorage()->loadSettings();
        $base = rtrim($tmpDir ?? (string) ($settings['tmp_dir'] ?? '/tmp'), '/');

        $this->binary = (string) ($settings['tsp_binary'] ?? 'tsp');
        $this->tmpDir = $base . '/tsp';
        $this->socket = $socket ?? $this->tmpDir . '/socket';

        $slots = $settings['tsp_slots'] ?? null;
        $this->slots = $slots !== null ? (string) $slots : null;
    }

    /**
     * Ставит команду в очередь с меткой label.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{id: int, label: string}
     */
    public function enqueue(string $label, array $command, array $env = []): array
    {
        $result = $this->run(array_merge(['-L', $label], $command), $env);

        $id = -1;
        if ($result['exitCode'] === 0 && preg_match('/^\s*(\d+)\s*$/m', $result['stdout'], $m)) {
            $id = (int) $m[1];
        }

        return ['id' => $id, 'label' => $label];
    }

    /**
     * Список задач очереди (tsp -l).
     *
     * Парсинг `tsp -l` хрупок: формат колонок зависит от версии tsp.
     * Здесь извлекается надёжно только то, что нужно менеджеру задач:
     * id, state, label (если метка в формате user#hex) и хвост строки
     * для отображения команды.
     *
     * @return array<int, array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}>
     */
    public function list(): array
    {
        $result = $this->run(['-l']);

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $jobs = [];
        foreach (preg_split('/\r\n|\r|\n/', $result['stdout']) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'ID')) {
                continue;
            }

            if (!preg_match('/^(\d+)\s+(\S+)\s*(.*)$/', $line, $m)) {
                continue;
            }

            $rest = $m[3];
            $label = null;
            if (preg_match('/([A-Za-z0-9._-]+#[0-9a-f]{8,})/', $rest, $lm)) {
                $label = $lm[1];
            }

            $jobs[] = [
                'id' => (int) $m[1],
                'state' => $m[2],
                'command' => $rest,
                'label' => $label,
                'output' => null,
                'errorlevel' => null,
            ];
        }

        return $jobs;
    }

    /**
     * Состояние задачи (tsp -s <id>).
     */
    public function state(int $id): string
    {
        $result = $this->run(['-s', (string) $id]);
        return $result['exitCode'] === 0 ? trim($result['stdout']) : 'unknown';
    }

    /**
     * Имя output-файла задачи (tsp -o <id>).
     */
    public function outputFile(int $id): ?string
    {
        $result = $this->run(['-o', (string) $id]);
        $path = $result['exitCode'] === 0 ? trim($result['stdout']) : '';
        return $path !== '' ? $path : null;
    }

    /**
     * Полный вывод задачи (tsp -c <id>). Блокируется до завершения задачи.
     */
    public function cat(int $id): string
    {
        $result = $this->run(['-c', (string) $id]);
        return $result['exitCode'] === 0 ? $result['stdout'] : '';
    }

    /**
     * Полный вывод задачи вместе с кодом возврата (tsp -c <id>).
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function catRaw(int $id): array
    {
        return $this->run(['-c', (string) $id]);
    }

    /**
     * Хвост вывода задачи (tsp -t <id>).
     */
    public function tail(int $id): string
    {
        $result = $this->run(['-t', (string) $id]);
        return $result['exitCode'] === 0 ? $result['stdout'] : '';
    }

    /**
     * Ожидание завершения задачи (tsp -w <id>). Возвращает код возврата задачи.
     */
    public function wait(int $id): int
    {
        $result = $this->run(['-w', (string) $id]);
        return $result['exitCode'];
    }

    /**
     * Информация о задаче (tsp -i <id>).
     *
     * @return array{label: ?string, command: ?string}
     */
    public function info(int $id): array
    {
        $result = $this->run(['-i', (string) $id]);
        $text = $result['exitCode'] === 0 ? $result['stdout'] : '';

        $label = null;
        if (preg_match('/([A-Za-z0-9._-]+#[0-9a-f]{8,})/', $text, $m)) {
            $label = $m[1];
        }

        $command = null;
        if (preg_match('/^Command:\s*(.+)$/m', $text, $m)) {
            $command = trim($m[1]);
        }

        return ['label' => $label, 'command' => $command];
    }

    /**
     * Удаляет задачу из очереди (tsp -r <id>).
     */
    public function remove(int $id): void
    {
        $this->run(['-r', (string) $id]);
    }

    /**
     * Убивает процесс-группу задачи (tsp -k <id>, SIGTERM).
     */
    public function kill(int $id): void
    {
        $this->run(['-k', (string) $id]);
    }

    /**
     * Очищает результаты завершённых задач (tsp -C).
     */
    public function clearFinished(): void
    {
        $this->run(['-C']);
    }

    /**
     * Запускает `tsp` с настройками очереди (TMPDIR/TS_SOCKET/TS_SLOTS),
     * поверх которых накладывается окружение задачи.
     *
     * @param array<int, string> $args
     * @param array<string, string> $env
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function run(array $args, array $env = []): array
    {
        $merged = [
            'TMPDIR' => $this->tmpDir,
            'TS_SOCKET' => $this->socket,
        ];
        if ($this->slots !== null) {
            $merged['TS_SLOTS'] = $this->slots;
        }
        foreach ($env as $key => $value) {
            $merged[$key] = $value;
        }

        return $this->runner->run(array_merge([$this->binary], $args), $merged, null, 0);
    }
}
