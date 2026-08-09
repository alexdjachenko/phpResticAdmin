<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Core;

class Session
{
    private bool $started = false;

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->started = true;
    }

    /**
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->started = false;
    }

    /**
     * Устанавливает или получает flash-сообщение (самоудаляется после чтения).
     *
     * @return string|null
     */
    public function flash(string $key, ?string $message = null): ?string
    {
        $flashKey = '_flash_' . $key;

        if ($message !== null) {
            $_SESSION[$flashKey] = $message;
            return null;
        }

        $value = $_SESSION[$flashKey] ?? null;
        unset($_SESSION[$flashKey]);

        return $value;
    }
}
