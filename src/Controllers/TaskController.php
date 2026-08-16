<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;

/**
 * Web-роуты фоновых задач: стриминг вывода и polling статуса.
 */
class TaskController
{
    /**
     * GET /tasks/stream?label=... — стримит вывод задачи в браузер.
     */
    public function stream(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->redirect('/login');
            return;
        }

        $request = new Request();
        $label = (string) $request->get('label', '');

        if ($label === '' || !App::tasks()->isValidLabel($label)) {
            App::response()->error(400, 'Invalid task label');
            return;
        }

        $prefix = null;
        if ($request->get('dry_run', '0') === '1') {
            $prefix = __('maint.dry_run_note');
        }

        App::tasks()->streamOutput($user, $label, $auth->canManageProcesses(), $prefix);
    }

    /**
     * GET /tasks/status?label=... — JSON-статус задачи (для polling).
     */
    public function status(): void
    {
        $auth = App::auth();
        $user = $auth->user();

        if ($user === null) {
            App::response()->json(['ok' => false, 'error' => 'Authentication required', '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $request = new Request();
        $label = (string) $request->get('label', '');

        if ($label === '' || !App::tasks()->isValidLabel($label)) {
            App::response()->json(['ok' => false, 'error' => 'Invalid task label', '_csrf_token' => App::security()->csrfToken()], 400);
            return;
        }

        $status = App::tasks()->status($user, $label, $auth->canManageProcesses());

        if ($status === null) {
            App::response()->json(['ok' => false, 'error' => __('error.forbidden'), '_csrf_token' => App::security()->csrfToken()], 403);
            return;
        }

        $status['ok'] = true;
        $status['_csrf_token'] = App::security()->csrfToken();

        App::response()->json($status);
    }
}
