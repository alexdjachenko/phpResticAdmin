<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

/**
 * Базовый контроллер.
 *
 * Собирает повторяющийся блок «auth → CSRF → поиск репозитория → право →
 * JSON-ошибка», чтобы контроллеры занимались только диспетчеризацией.
 * Поведение (статусы, редиректы, права) сохраняется.
 *
 * Важно: при AJAX-запросе (fetch) любой отказ (нет прав, истёк CSRF) обязан
 * вернуть JSON, а не редирект/HTML. Иначе fetch не сможет разобрать ответ и
 * покажет общую ошибку вместо настоящей причины. Поэтому хелперы сами
 * определяют AJAX и в этом случае отвечают JSON.
 */
abstract class BaseController
{
    protected function request(): Request
    {
        return new Request();
    }

    protected function isAjax(): bool
    {
        return $this->request()->isAjax();
    }

    /**
     * Требует аутентификации.
     *
     * @param bool $json true — всегда JSON 403, false — JSON только для AJAX
     * @return string|null имя пользователя или null (ответ уже отправлен)
     */
    protected function requireUser(bool $json = false): ?string
    {
        $user = App::auth()->user();

        if ($user === null) {
            if ($json || $this->isAjax()) {
                $this->jsonError('Authentication required', 403);
            } else {
                App::response()->redirect('/login');
            }
        }

        return $user;
    }

    /**
     * Ищет репозиторий по id среди доступных пользователю.
     *
     * @return array<string, mixed>|null
     */
    protected function findRepo(string $user, string $repoId): ?array
    {
        if ($repoId === '') {
            return null;
        }

        foreach (App::repoStorage()->loadAll($user) as $repo) {
            if (($repo['id'] ?? '') === $repoId) {
                return $repo;
            }
        }

        return null;
    }

    /**
     * Разрешает репозиторий и проверяет право на категорию.
     *
     * @param string $permission use|read|write|edit
     * @param bool $json true — JSON при отказе, false — только для AJAX
     * @return array<string, mixed>|null
     */
    protected function requireRepo(string $user, string $repoId, string $permission, bool $json = false): ?array
    {
        $repo = $this->findRepo($user, $repoId);

        if ($repo === null) {
            $this->abort(404, $json);
            return null;
        }

        if (!$this->can($permission, (string) ($repo['category'] ?? 'public'))) {
            $this->abort(403, $json);
            return null;
        }

        return $repo;
    }

    /**
     * Проверяет CSRF-токен.
     *
     * @param bool $json true — JSON-ответ при ошибке, false — только для AJAX
     * @param string $fallback куда вернуться, если нет Referer
     */
    protected function requireCsrf(bool $json = false, string $fallback = '/'): bool
    {
        if (App::security()->validateCsrf((string) $this->request()->post('_csrf_token', ''))) {
            return true;
        }

        App::log('CSRF validation failed: ' . $this->request()->method() . ' ' . $this->request()->uri() . ($this->isAjax() ? ' (ajax)' : ''), 1);

        if ($json || $this->isAjax()) {
            $this->jsonError(__('flash.csrf_error'), 403);
        }

        App::session()->flash('error', __('flash.csrf_error'));
        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function jsonOk(array $data = []): void
    {
        $data['ok'] = $data['ok'] ?? true;
        $data['_csrf_token'] = App::security()->csrfToken();

        App::response()->json($data);
    }

    protected function jsonError(string $error, int $code = 400): void
    {
        App::response()->json([
            'ok' => false,
            'error' => $error,
            '_csrf_token' => App::security()->csrfToken(),
        ], $code);
    }

    /**
     * @param array<string, mixed> $vars
     */
    protected function render(string $template, array $vars = []): void
    {
        echo App::response()->render($template, $vars);
    }

    /**
     * Пишет flash «задача запущена» (навигация — дело фронтенда, хранить нечего).
     */
    protected function taskStarted(string $label, string $title): void
    {
        App::session()->flash('success', __('flash.task_started', ['{title}' => $title]));
    }

    /**
     * Ответ после старта фоновой задачи.
     *
     * Принимает результат `start*()` сервиса (`{label, id}`): если tsp не смог
     * поставить задачу (id < 0 или пустая метка), отвечаем ошибкой, а НЕ меткой —
     * иначе фронтенд откроет модалку с несуществующей задачей.
     *
     * AJAX (fetch) — JSON с меткой/заголовком; обычная отправка формы —
     * редирект назад с flash (без текстового терминала).
     *
     * @param array{label?: string, id?: int} $started
     */
    protected function respondTaskStarted(array $started, string $title, bool $dryRun = false, string $fallback = '/'): void
    {
        $label = (string) ($started['label'] ?? '');
        $id = (int) ($started['id'] ?? -1);

        if ($label === '' || $id < 0) {
            $this->respondTaskFailed($title, $fallback);
            return;
        }

        if ($this->isAjax()) {
            $url = '/tasks/stream?label=' . urlencode($label);
            if ($dryRun) {
                $url .= '&dry_run=1';
            }

            $this->jsonOk([
                'label' => $label,
                'title' => $title,
                'dry_run' => $dryRun,
                'stream_url' => $url,
            ]);
            return;
        }

        $this->taskStarted($label, $title);
        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? $fallback, 303);
    }

    /**
     * Задача не поставлена (tsp недоступен/очередь отвергла): ошибка вместо метки.
     */
    protected function respondTaskFailed(string $title, string $fallback = '/'): void
    {
        App::log('Failed to start background task: ' . $title, 0);

        if ($this->isAjax()) {
            $this->jsonError(__('tasks.start_failed'), 500);
            return;
        }

        App::session()->flash('error', __('tasks.start_failed'));
        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? $fallback, 303);
    }

    /**
     * Ошибка валидации до старта задачи: JSON для AJAX, flash+редирект иначе.
     * Нужен там, где раньше был безусловный flash+redirect (AJAX-клиент ожидает JSON).
     */
    protected function redirectOrJson(string $error, string $fallback = '/'): void
    {
        if ($this->isAjax()) {
            $this->jsonError($error, 400);
            return;
        }

        App::session()->flash('error', $error);
        App::response()->redirect($fallback);
    }

    /**
     * id репозитория для страницы: ?repo → current_repo → null.
     */
    protected function resolveRepoId(Request $request): ?string
    {
        $repoId = $request->get('repo', '');
        if ($repoId !== '') {
            return (string) $repoId;
        }

        $sessionRepoId = App::session()->get('current_repo');

        return $sessionRepoId !== null ? (string) $sessionRepoId : null;
    }

    /**
     * Отправляет 403/404: JSON ($json или AJAX) либо HTML-страницу.
     */
    protected function abort(int $code, bool $json, ?string $message = null): void
    {
        $message ??= $code === 403 ? __('error.forbidden') : __('flash.not_found');

        if ($json || $this->isAjax()) {
            $this->jsonError($message, $code);
            return;
        }

        App::response()->error($code, $message);
    }

    private function can(string $permission, string $category): bool
    {
        $auth = App::auth();

        return match ($permission) {
            'read' => $auth->canUseRead($category),
            'write' => $auth->canUseWrite($category),
            'edit' => $auth->canEdit($category),
            default => $auth->canUse($category),
        };
    }
}
