<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

class KeyController
{
    /**
     * GET /keys — список ключей репозитория.
     */
    public function list(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $repoId = $this->resolveRepoId($request);

        if ($repoId === null) {
            App::session()->flash('success', __('flash.select_repo'));
            App::response()->redirect('/repositories');
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

        $keyService = App::keyService();
        $keys = $keyService->listKeys($repo);

        // Бейдж «реквизиты источника» считается по листингу с паролем реквизитов
        // и не зависит от «Проверить пароль» (AJAX-подсветка — отдельно).
        $hasPassword = !empty($repo['password']);
        $workingKeyId = $keyService->workingKeyId($repo);
        $credentialsMismatch = $hasPassword && $workingKeyId === null;

        echo App::response()->render('keys/list.php', [
            'repo' => $repo,
            'keys' => $keys,
            'workingKeyId' => $workingKeyId,
            'hasPassword' => $hasPassword,
            'credentialsMismatch' => $credentialsMismatch,
            'canWrite' => $auth->canUseWrite($category),
            'canEdit' => $auth->canEdit($category),
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => $auth->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /keys/verify — идентификация ключа по введённому паролю (AJAX).
     */
    public function verify(): void
    {
        $ctx = $this->resolveRepoForAction('canUseRead');
        if ($ctx === null) {
            return;
        }
        [$repo] = $ctx;

        $request = new Request();
        $password = (string) $request->post('password', '');
        if ($password === '') {
            $this->json(['ok' => false, 'error' => __('keys.verify_fail')]);
            return;
        }

        $keyService = App::keyService();
        $identified = $keyService->identifyKey($repo, $password);

        if ($identified === null) {
            $this->json(['ok' => false, 'error' => __('keys.verify_fail')]);
            return;
        }

        $role = ($keyService->workingKeyId($repo) === $identified['id']) ? 'source' : 'extra';
        $this->json([
            'ok' => true,
            'key_id' => $identified['id'],
            'short_id' => substr($identified['id'], 0, 8),
            'role' => $role,
            'message' => __('keys.verify_ok'),
        ]);
    }

    /**
     * POST /keys/add — добавить пароль (AJAX).
     */
    public function add(): void
    {
        $ctx = $this->resolveRepoForAction('canUseWrite');
        if ($ctx === null) {
            return;
        }
        [$repo] = $ctx;

        $request = new Request();
        $newPassword = (string) $request->post('new_password', '');
        if ($newPassword === '') {
            $this->json(['ok' => false, 'error_code' => 'failed', 'error' => __('keys.add_error')]);
            return;
        }

        $result = App::keyService()->addKey($repo, $newPassword);
        $this->json($this->keyResult($result));
    }

    /**
     * POST /keys/remove — удаление ключа по id или по паролю (AJAX).
     */
    public function remove(): void
    {
        $ctx = $this->resolveRepoForAction('canUseWrite');
        if ($ctx === null) {
            return;
        }
        [$repo] = $ctx;

        $request = new Request();
        $keyId = (string) $request->post('key_id', '');
        $password = (string) $request->post('password', '');

        if ($keyId !== '') {
            $result = App::keyService()->removeKey($repo, $keyId);
        } elseif ($password !== '') {
            $result = App::keyService()->removeKeyByPassword($repo, $password);
        } else {
            $this->json(['ok' => false, 'error_code' => 'not_found', 'error' => '']);
            return;
        }

        $this->json($this->keyResult($result));
    }

    /**
     * POST /keys/passwd — смена пароля ключа (AJAX).
     *
     * Порядок: сначала changePassword(), сразу за ним — сохранение реквизитов
     * (если меняется рабочий ключ), и только потом ответ. Если запись реквизитов
     * не удалась, показываем новый пароль и пишем в лог уровня 0: без реквизитов
     * доступ из UI к репозиторию сломан.
     */
    public function passwd(): void
    {
        $ctx = $this->resolveRepoForAction('canUseWrite');
        if ($ctx === null) {
            return;
        }
        [$repo, $category, $repoId, $user] = $ctx;

        $request = new Request();
        $oldPassword = (string) $request->post('old_password', '');
        $newPassword = (string) $request->post('new_password', '');
        $updateCredentials = $request->post('update_credentials', '0') === '1';

        if ($oldPassword === '' || $newPassword === '') {
            $this->json(['ok' => false, 'error_code' => 'failed', 'error' => __('keys.add_error')]);
            return;
        }

        $keyService = App::keyService();
        $identified = $keyService->identifyKey($repo, $oldPassword);
        $workingKeyId = $keyService->workingKeyId($repo);
        $isWorkingKey = $identified !== null && $workingKeyId !== null && $identified['id'] === $workingKeyId;

        // Галочка «обновить реквизиты» срабатывает только для рабочего ключа.
        if ($updateCredentials && $isWorkingKey && !App::auth()->canEdit($category)) {
            $this->json(['ok' => false, 'error_code' => 'no_edit_right', 'error' => __('keys.no_edit_right')]);
            return;
        }

        $result = $keyService->changePassword($repo, $oldPassword, $newPassword);

        if (!$result['ok']) {
            $this->json($this->keyResult($result));
            return;
        }

        $response = $this->keyResult($result);
        $response['credentials_updated'] = false;
        $response['is_working_key'] = $isWorkingKey;

        if ($updateCredentials && $isWorkingKey) {
            $saved = false;
            try {
                App::repoStorage()->update($category, $repoId, ['password' => $newPassword], $user);
                $saved = true;
            } catch (\Throwable $e) {
                App::log('Failed to save repository credentials after key passwd: ' . $e->getMessage(), 0);
            }

            $response['credentials_updated'] = $saved;
            if (!$saved) {
                // Критическая ошибка: старый ключ уже удалён, новый не сохранён.
                $response['new_password'] = $newPassword;
                App::log('Repository credentials were NOT saved after key passwd; manual recovery required', 0);
            }
        }

        $this->json($response);
    }

    /**
     * Общий ответ по результату операции с ключом.
     *
     * @param array{ok: bool, key_id?: ?string, error_code?: ?string, error?: string} $result
     * @return array<string, mixed>
     */
    private function keyResult(array $result): array
    {
        $errorCode = $result['error_code'] ?? null;
        $detail = (string) ($result['error'] ?? '');

        return [
            'ok' => $result['ok'],
            'key_id' => $result['key_id'] ?? null,
            'short_id' => !empty($result['key_id']) ? substr((string) $result['key_id'], 0, 8) : null,
            'error_code' => $errorCode,
            'error' => $errorCode !== null ? __('keys.err_' . $errorCode) . ($detail !== '' ? ': ' . $detail : '') : '',
        ];
    }

    /**
     * Разрешает репозиторий и право для POST-операции с ключом.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string, 3: string}|null
     */
    private function resolveRepoForAction(string $permission): ?array
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            $this->json(['ok' => false, 'error' => 'Authentication required'], 403);
            return null;
        }

        $request = new Request();
        if (!App::security()->validateCsrf((string) $request->post('_csrf_token', ''))) {
            $this->json(['ok' => false, 'error' => __('flash.csrf_error')], 403);
            return null;
        }

        $repoId = (string) $request->post('repo_id', '');
        if ($repoId === '') {
            $this->json(['ok' => false, 'error' => __('flash.not_found')], 404);
            return null;
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
            $this->json(['ok' => false, 'error' => __('flash.not_found')], 404);
            return null;
        }

        $category = $repo['category'] ?? 'public';
        $allowed = $permission === 'canUseWrite' ? $auth->canUseWrite($category) : $auth->canUseRead($category);
        if (!$allowed) {
            $this->json(['ok' => false, 'error' => __('error.forbidden')], 403);
            return null;
        }

        return [$repo, $category, $repoId, $user];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $code = 200): void
    {
        $data['_csrf_token'] = App::security()->csrfToken();
        App::response()->json($data, $code);
    }

    private function resolveRepoId(Request $request): ?string
    {
        $repoId = $request->get('repo', '');
        if ($repoId !== '') {
            return $repoId;
        }
        $sessionRepoId = App::session()->get('current_repo');
        if ($sessionRepoId !== null) {
            return $sessionRepoId;
        }
        return null;
    }
}
