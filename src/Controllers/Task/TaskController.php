<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Task;

use App\Controllers\BaseController;
use App\Core\App;
use App\Helpers\Format;

/**
 * Web-роуты фоновых задач.
 *
 * tsp — единственный источник правды о задачах; из метки (TaskLabel)
 * извлекаются операция, репозиторий и владелец, чтобы показать человеческое
 * имя задачи. Никакого второго хранилища задач нет.
 */
class TaskController extends BaseController
{
    /**
     * GET /tasks — список задач, видимых пользователю.
     */
    public function list(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $privileged = App::auth()->canManageProcesses();
        $tasks = $this->annotate(App::tasks()->listForUser($user, $privileged), $user);

        $this->render('tasks/list.php', [
            'tasks' => $tasks,
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * GET /tasks/view?label=... — страница задачи (для прямых ссылок).
     */
    public function view(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $label = (string) $this->request()->get('label', '');
        $privileged = App::auth()->canManageProcesses();

        if ($label === '' || !App::tasks()->assertAccess($user, $label, $privileged)) {
            App::response()->error(404, 'Task not found');
            return;
        }

        $described = App::tasks()->describe($label);
        $job = App::tasks()->findByLabel($label);

        $this->render('tasks/view.php', [
            'label' => $label,
            'title' => $described['title'] ?? $label,
            'state' => $job['state'] ?? 'unknown',
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * GET /tasks/stream?label=... — сырой вывод задачи (для <pre>/fetch).
     */
    public function stream(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $label = (string) $this->request()->get('label', '');

        if ($label === '' || !App::tasks()->isValidLabel($label)) {
            App::response()->error(400, 'Invalid task label');
            return;
        }

        $prefix = null;
        if ($this->request()->get('dry_run', '0') === '1') {
            $prefix = __('maint.dry_run_note');
        }

        // Освобождаем сессию ПОСЛЕДНИМ действием перед стримингом: до этого
        // момента могли выпускаться CSRF-токены/flash, после — запись в сессию
        // уже не сохранится.
        App::session()->close();

        App::tasks()->streamOutput($user, $label, App::auth()->canManageProcesses(), $prefix);
    }

    /**
     * GET /tasks/status?label=... — JSON-статус задачи (state, exitCode, позиция).
     */
    public function status(): void
    {
        $user = $this->requireUser(true);
        if ($user === null) {
            return;
        }

        $label = (string) $this->request()->get('label', '');

        if ($label === '' || !App::tasks()->isValidLabel($label)) {
            $this->jsonError('Invalid task label', 400);
            return;
        }

        $status = App::tasks()->status($user, $label, App::auth()->canManageProcesses());

        if ($status === null) {
            $this->jsonError(__('error.forbidden'), 403);
            return;
        }

        $status['ok'] = true;
        $this->jsonOk($status);
    }

    /**
     * GET /tasks/active — JSON-список задач для индикатора в шапке.
     */
    public function active(): void
    {
        $user = $this->requireUser(true);
        if ($user === null) {
            return;
        }

        $privileged = App::auth()->canManageProcesses();
        $tasks = $this->annotate(App::tasks()->listForUser($user, $privileged), $user);

        $active = 0;
        foreach ($tasks as $task) {
            if (in_array($task['state'], ['queued', 'running'], true)) {
                $active++;
            }
        }

        $settings = App::configStorage()->loadSettings();

        $this->jsonOk([
            'tasks' => $tasks,
            'active' => $active,
            'poll_interval' => (int) ($settings['task_poll_interval'] ?? 3000),
        ]);
    }

    /**
     * POST /tasks/cancel — отмена задачи (tsp -k).
     */
    public function cancel(): void
    {
        $this->changeState('cancel');
    }

    /**
     * POST /tasks/promote — поднять задачу в начало очереди (tsp -u).
     */
    public function promote(): void
    {
        $this->changeState('promote');
    }

    /**
     * Общая логика cancel/promote: CSRF, права, JSON-ответ.
     */
    private function changeState(string $action): void
    {
        $user = $this->requireUser(true);
        if ($user === null) {
            return;
        }

        if (!$this->requireCsrf(true)) {
            return;
        }

        $label = (string) $this->request()->post('label', '');
        if ($label === '' || !App::tasks()->isValidLabel($label)) {
            $this->jsonError('Invalid task label', 400);
            return;
        }

        $privileged = App::auth()->canManageProcesses();
        $tasks = App::tasks();

        $ok = $action === 'cancel'
            ? $tasks->cancel($user, $label, $privileged)
            : $tasks->promote($user, $label, $privileged);

        $this->jsonOk([
            'ok' => $ok,
            'error' => $ok ? null : __('error.forbidden'),
        ]);
    }

    /**
     * Обогащает задачи человеческим заголовком, владельцем и именем репозитория.
     *
     * @param array<int, array<string, mixed>> $jobs
     * @return array<int, array{id: int, state: string, position: ?int, label: string, valid: bool, title: string, owner: ?string, op: ?string, repoId: ?string, repoName: ?string, command: string}>
     */
    private function annotate(array $jobs, string $user): array
    {
        $repoNames = [];
        foreach (App::repoStorage()->loadAll($user) as $r) {
            $repoNames[$r['id'] ?? ''] = $r['name'] ?? '';
        }

        $tasks = App::tasks();
        $rows = [];

        foreach ($jobs as $job) {
            $label = (string) ($job['label'] ?? '');
            $parsed = $label !== '' ? $tasks->parseLabel($label) : null;
            $described = $label !== '' ? $tasks->describe($label) : null;

            $repoId = $described['repoId'] ?? null;
            $state = (string) ($job['state'] ?? 'unknown');

            $rows[] = [
                'id' => (int) $job['id'],
                'state' => $state,
                'position' => $state === 'queued' && $label !== '' ? $tasks->queuePosition($label) : null,
                'label' => $label,
                'valid' => $described !== null,
                'title' => $described['title'] ?? ($label !== '' ? $label : '—'),
                'owner' => $parsed['username'] ?? null,
                'op' => $described['op'] ?? null,
                'repoId' => $repoId,
                'repoName' => $repoId !== null ? ($repoNames[$repoId] ?? $repoId) : null,
                'command' => Format::truncate((string) ($job['command'] ?? ''), 80),
            ];
        }

        return $rows;
    }
}
