<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Key;

use App\Controllers\BaseController;
use App\Core\App;

class KeyController extends BaseController
{
    /**
     * GET /keys — список ключей репозитория.
     */
    public function list(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = $this->resolveRepoId($this->request());

        if ($repoId === null) {
            App::session()->flash('success', __('flash.select_repo'));
            App::response()->redirect('/repositories');
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $category = $repo['category'] ?? 'public';
        $keyService = App::keyService();
        $keys = $keyService->listKeys($repo);

        // Бейдж «реквизиты источника» считается по листингу с паролем реквизитов
        // и не зависит от «Проверить пароль» (AJAX-подсветка — отдельно).
        $hasPassword = !empty($repo['password']);
        $workingKeyId = $keyService->workingKeyId($repo);
        $credentialsMismatch = $hasPassword && $workingKeyId === null;

        $this->render('keys/list.php', [
            'repo' => $repo,
            'keys' => $keys,
            'workingKeyId' => $workingKeyId,
            'hasPassword' => $hasPassword,
            'credentialsMismatch' => $credentialsMismatch,
            'canWrite' => App::auth()->canUseWrite($category),
            'canEdit' => App::auth()->canEdit($category),
            'csrfToken' => App::security()->csrfToken(),
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
        ]);
    }

    /**
     * POST /keys/verify — идентификация ключа по введённому паролю (AJAX).
     */
    public function verify(): void
    {
        $repo = $this->resolveRepoForAction('read');
        if ($repo === null) {
            return;
        }

        $password = (string) $this->request()->post('password', '');
        if ($password === '') {
            $this->keyJson(['ok' => false, 'error' => __('keys.verify_fail')]);
            return;
        }

        $keyService = App::keyService();
        $identified = $keyService->identifyKey($repo, $password);

        if ($identified === null) {
            $this->keyJson(['ok' => false, 'error' => __('keys.verify_fail')]);
            return;
        }

        $role = ($keyService->workingKeyId($repo) === $identified['id']) ? 'source' : 'extra';
        $this->keyJson([
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
        $repo = $this->resolveRepoForAction('write');
        if ($repo === null) {
            return;
        }

        $newPassword = (string) $this->request()->post('new_password', '');
        if ($newPassword === '') {
            $this->keyJson(['ok' => false, 'error_code' => 'failed', 'error' => __('keys.add_error')]);
            return;
        }

        $result = App::keyService()->addKey($repo, $newPassword);
        $this->keyJson($this->keyResult($result));
    }

    /**
     * POST /keys/remove — удаление ключа по id или по паролю (AJAX).
     */
    public function remove(): void
    {
        $repo = $this->resolveRepoForAction('write');
        if ($repo === null) {
            return;
        }

        $keyId = (string) $this->request()->post('key_id', '');
        $password = (string) $this->request()->post('password', '');

        if ($keyId !== '') {
            $result = App::keyService()->removeKey($repo, $keyId);
        } elseif ($password !== '') {
            $result = App::keyService()->removeKeyByPassword($repo, $password);
        } else {
            $this->keyJson(['ok' => false, 'error_code' => 'not_found', 'error' => '']);
            return;
        }

        $this->keyJson($this->keyResult($result));
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
        $repo = $this->resolveRepoForAction('write');
        if ($repo === null) {
            return;
        }

        $request = $this->request();
        $oldPassword = (string) $request->post('old_password', '');
        $newPassword = (string) $request->post('new_password', '');
        $updateCredentials = $request->post('update_credentials', '0') === '1';

        if ($oldPassword === '' || $newPassword === '') {
            $this->keyJson(['ok' => false, 'error_code' => 'failed', 'error' => __('keys.add_error')]);
            return;
        }

        $keyService = App::keyService();
        $identified = $keyService->identifyKey($repo, $oldPassword);
        $workingKeyId = $keyService->workingKeyId($repo);
        $isWorkingKey = $identified !== null && $workingKeyId !== null && $identified['id'] === $workingKeyId;

        $category = (string) ($repo['category'] ?? 'public');

        // Галочка «обновить реквизиты» срабатывает только для рабочего ключа.
        if ($updateCredentials && $isWorkingKey && !App::auth()->canEdit($category)) {
            $this->keyJson(['ok' => false, 'error_code' => 'no_edit_right', 'error' => __('keys.no_edit_right')]);
            return;
        }

        $result = $keyService->changePassword($repo, $oldPassword, $newPassword);

        if (!$result['ok']) {
            $this->keyJson($this->keyResult($result));
            return;
        }

        $response = $this->keyResult($result);
        $response['credentials_updated'] = false;
        $response['is_working_key'] = $isWorkingKey;

        if ($updateCredentials && $isWorkingKey) {
            $repoId = (string) $request->post('repo_id', '');
            $user = App::auth()->user() ?? '';

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

        $this->keyJson($response);
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
     * @param string $permission read|write
     * @return array<string, mixed>|null
     */
    private function resolveRepoForAction(string $permission): ?array
    {
        if ($this->requireUser(true) === null) {
            return null;
        }

        if (!$this->requireCsrf(true)) {
            return null;
        }

        $repoId = (string) $this->request()->post('repo_id', '');
        if ($repoId === '') {
            $this->jsonError(__('flash.not_found'), 404);
            return null;
        }

        $user = App::auth()->user() ?? '';

        return $this->requireRepo($user, $repoId, $permission, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function keyJson(array $data, int $code = 200): void
    {
        $data['_csrf_token'] = App::security()->csrfToken();
        App::response()->json($data, $code);
    }
}
