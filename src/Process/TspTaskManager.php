<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Process;

use App\Core\App;

/**
 * Менеджер фоновых задач поверх TspClient.
 *
 * Метка задачи строится и разбирается через TaskLabel (единственный источник
 * правды о схеме). Менеджер не хранит состояние задач: tsp — единственный
 * источник правды, а из метки извлекаются операция, репозиторий и владелец.
 *
 * Обычный пользователь видит только свои задачи; пользователь с
 * `can_manage_processes` видит все.
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
     * Метка строится через TaskLabel::build(): `<username>#<op><repoId><rand16>`.
     *
     * `$separateStderr = true` включает `tsp -E`: stdout и stderr задачи пишутся
     * в разные файлы (нужно для JSON-задач).
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{label: string, id: int}
     */
    public function start(
        string $username,
        string $op,
        ?string $repoId,
        array $command,
        array $env = [],
        bool $separateStderr = false
    ): array {
        $label = TaskLabel::build($username, $op, $repoId);
        return $this->tsp->enqueue($label, $command, $env, $separateStderr);
    }

    /**
     * Задачи, видимые пользователю.
     *
     * @return array<int, array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}>
     */
    public function listForUser(string $username, bool $privileged): array
    {
        $jobs = $this->all();

        if ($privileged) {
            return $jobs;
        }

        return array_values(array_filter($jobs, function (array $job) use ($username): bool {
            $label = $job['label'] ?? '';
            return $label !== '' && str_starts_with($label, $username . '#');
        }));
    }

    /**
     * Активные задачи по операции и репозиторию, по всем пользователям.
     *
     * Лок restic глобальный, поэтому чужая задача по тому же репозиторию
     * блокирует нашу независимо от авторства — прятать её нельзя. Возвращаются
     * задачи (любого пользователя) с совпадающими op и repoId.
     *
     * @return array<int, array{id: int, state: string, label: string, username: string, op: string, repoId: ?string}>
     */
    public function listActiveFor(string $op, ?string $repoId): array
    {
        $result = [];
        $repoId = $repoId !== '' ? $repoId : null;

        foreach ($this->all() as $job) {
            $label = $job['label'] ?? '';
            $parsed = $label !== '' ? TaskLabel::parse($label) : null;
            if ($parsed === null || $parsed['op'] !== $op) {
                continue;
            }
            if (($parsed['repoId'] ?? null) !== $repoId) {
                continue;
            }

            $result[] = [
                'id' => $job['id'],
                'state' => $job['state'],
                'label' => $label,
                'username' => $parsed['username'],
                'op' => $parsed['op'],
                'repoId' => $parsed['repoId'],
            ];
        }

        return $result;
    }

    /**
     * Человеческое описание задачи по метке.
     *
     * @return array{title: string, op: string, repoId: ?string}|null
     */
    public function describe(string $label): ?array
    {
        $parsed = TaskLabel::parse($label);
        if ($parsed === null) {
            return null;
        }

        return [
            'title' => __('tasks.op_' . $parsed['op']),
            'op' => $parsed['op'],
            'repoId' => $parsed['repoId'],
        ];
    }

    /**
     * Позиция задачи в очереди.
     *
     * N = число задач, стоящих в очереди перед ней (1 = следующая на запуск).
     * null — если задача не найдена в очереди.
     */
    public function queuePosition(string $label): ?int
    {
        $position = 0;

        foreach ($this->all() as $job) {
            $jobLabel = $job['label'] ?? '';

            if ($jobLabel === $label) {
                return $position + 1;
            }

            if (($job['state'] ?? '') === 'queued') {
                $position++;
            }
        }

        return null;
    }

    /**
     * @return array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}|null
     */
    public function findByLabel(string $label): ?array
    {
        foreach ($this->all() as $job) {
            if (($job['label'] ?? '') === $label) {
                return $job;
            }
        }
        return null;
    }

    /**
     * Статус задачи: state, exitCode (если известен), позиция и хвост вывода.
     *
     * Хвост включает и stderr (отдельный `.e`-файл для задач с `tsp -E`),
     * чтобы причина ошибки была видна и в опросе статуса.
     *
     * @return array{label: string, id: int, state: string, exitCode: ?int, position: ?int, output: string}|null
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
            'position' => $state === 'queued' ? $this->queuePosition($label) : null,
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
     * `output` — stdout задачи (нужен для парсинга JSON), `stderr` — отдельный
     * `.e`-файл (для задач с `tsp -E`), чтобы донести настоящую ошибку.
     *
     * @return array{exitCode: int, output: string, stderr: string}|null
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
            'stderr' => $this->readErrFile($job['id']),
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
     * Отменяет задачу (tsp -k).
     */
    public function cancel(string $username, string $label, bool $privileged): bool
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            return false;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            return false;
        }

        $this->tsp->kill($job['id']);
        return true;
    }

    /**
     * Поднимает задачу в начало очереди (tsp -u).
     */
    public function promote(string $username, string $label, bool $privileged): bool
    {
        if (!$this->assertAccess($username, $label, $privileged)) {
            return false;
        }

        $job = $this->findByLabel($label);
        if ($job === null) {
            return false;
        }

        $this->tsp->promote($job['id']);
        return true;
    }

    /**
     * Проверка доступа: своя задача — всегда; чужая — только привилегированный.
     */
    public function assertAccess(string $username, string $label, bool $privileged): bool
    {
        $parsed = TaskLabel::parse($label);
        if ($parsed === null) {
            return false;
        }

        if ($privileged) {
            return true;
        }

        return $parsed['username'] === $username;
    }

    /**
     * Стримит вывод задачи в браузер по мере появления.
     *
     * Вывод собирается из двух файлов: основного (stdout) и отдельного `.e`
     * (stderr, создаётся только для задач с `tsp -E`). После терминального
     * состояния делается финальный дозабор — иначе остаток вывода (в том числе
     * ошибка restic) может потеряться из-за гонки между чтением файла и
     * проверкой состояния.
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

        $outputFile = $this->tsp->outputFile($id);
        $errFile = $outputFile !== null ? $outputFile . '.e' : null;

        $outPos = 0;
        $errPos = 0;

        $pump = function () use ($outputFile, $errFile, &$outPos, &$errPos): void {
            $out = $this->readNewBytes($outputFile, $outPos);
            if ($out !== '') {
                echo $out;
            }
            $err = $this->readNewBytes($errFile, $errPos);
            if ($err !== '') {
                echo $err;
            }
            if ($out !== '' || $err !== '') {
                flush();
            }
        };

        while (true) {
            $pump();

            $state = $this->tsp->state($id);
            if (in_array($state, ['finished', 'skipped', 'unknown'], true)) {
                // Финальный дозабор: процесс завершён, новых байтов уже не будет.
                $pump();
                break;
            }

            usleep(200000);
        }

        exit;
    }

    /**
     * Валидация формата метки (делегирует в TaskLabel).
     */
    public function isValidLabel(string $label): bool
    {
        return TaskLabel::isValid($label);
    }

    /**
     * Разбор метки (делегирует в TaskLabel).
     *
     * @return array{username: string, op: string, repoId: ?string, rand: string}|null
     */
    public function parseLabel(string $label): ?array
    {
        return TaskLabel::parse($label);
    }

    /**
     * Список задач очереди с мемоизацией на текущий запрос.
     *
     * Проблема: `tsp -l` вызывался до трёх раз за рендер (findByLabel + isFinished
     * + catResult). Список кешируется в области текущего запроса.
     *
     * @return array<int, array{id: int, state: string, command: string, label: ?string, output: ?string, errorlevel: ?int}>
     */
    private function all(): array
    {
        $list = App::cache()->request()->remember('tsp.list', null, fn () => $this->tsp->list());
        return is_array($list) ? $list : [];
    }

    /**
     * Читает хвост вывода задачи без блокировки (для status()):
     * основной output-файл плюс отдельный stderr (`.e`).
     */
    private function readTail(int $id, int $lines = 50): string
    {
        $data = $this->readTaskOutput($id);
        if ($data === '') {
            return '';
        }

        $chunks = explode("\n", $data);
        $tail = array_slice($chunks, -$lines);

        return implode("\n", $tail);
    }

    /**
     * Полный вывод задачи: основной output-файл + отдельный stderr (`.e`).
     */
    private function readTaskOutput(int $id): string
    {
        $outputFile = $this->tsp->outputFile($id);
        if ($outputFile === null) {
            return '';
        }

        $data = $this->readFile($outputFile);
        $err = $this->readFile($outputFile . '.e');

        if ($err !== '') {
            $data = $data === '' ? $err : rtrim($data) . "\n" . $err;
        }

        return $data;
    }

    /**
     * stderr задачи из отдельного `.e`-файла (создаётся только при `tsp -E`).
     */
    private function readErrFile(int $id): string
    {
        $outputFile = $this->tsp->outputFile($id);
        return $outputFile !== null ? $this->readFile($outputFile . '.e') : '';
    }

    /**
     * Безопасное чтение файла (отсутствие/ошибка → пустая строка).
     */
    private function readFile(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        $data = @file_get_contents($path);
        return $data === false ? '' : $data;
    }

    /**
     * Читает новые байты файла начиная с позиции $pos и сдвигает её вперёд.
     */
    private function readNewBytes(?string $file, int &$pos): string
    {
        if ($file === null || !file_exists($file)) {
            return '';
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return '';
        }

        if (fseek($handle, $pos) !== 0) {
            fclose($handle);
            return '';
        }

        $data = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }
        fclose($handle);

        $pos += strlen($data);

        return $data;
    }
}
