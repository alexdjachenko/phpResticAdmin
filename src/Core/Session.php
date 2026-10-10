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
        // Переоткрываем по фактическому статусу, а не по флагу: после close()
        // сессия должна открываться снова.
        if (session_status() === PHP_SESSION_NONE) {
            self::configureCookieParams();
            // Строгий режим: сервер не принимает чужой session id (защита от фиксации).
            @ini_set('session.use_strict_mode', '1');
            session_start();
        }

        $this->started = true;
    }

    /**
     * Закрывает сессию для записи, освобождая файловую блокировку.
     *
     * Вызывать ПОСЛЕДНИМ действием запроса: после этого запись в сессию не
     * сохраняется. До вызова должны быть выполнены все операции, пишущие в
     * сессию (flash-сообщения, выпуск нового CSRF-токена), иначе они потеряются.
     */
    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->started = false;
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

    /**
     * Настраивает cookie сессии (defense in depth).
     *
     * SameSite=Lax — браузер не отправляет cookie при cross-site POST, что
     * само по себе снимает классический CSRF (независимо от CSRF-токена).
     * HttpOnly — cookie недоступна из JS. Secure — только по HTTPS.
     * Вызывается ДО session_start().
     */
    private static function configureCookieParams(): void
    {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') === 'on') {
            return true;
        }

        if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
