<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Core;

class Security
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Токен CSRF, привязанный к сессии (synchronizer token pattern).
     *
     * Токен живёт всю сессию и НЕ меняется на каждый запрос — это устраняет
     * рассинхронизацию между носителями (data-csrf, скрытые поля форм) и между
     * вкладками: несколько вкладок делят одну сессию, и любой успешный POST в
     * одной вкладке не должен ломать токен в другой. Ротация — только при
     * входе/выходе (см. Authenticator).
     */
    public function csrfToken(): string
    {
        $token = $this->session->get('_csrf_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', $token);
        }

        return $token;
    }

    /**
     * Проверяет CSRF-токен.
     *
     * Сравнение через hash_equals; токен при проверке НЕ гасится (переиспользуемый).
     * Раньше токен был одноразовым, но AJAX-интерфейс с автополлингом задач и
     * несколькими носителями/вкладками постоянно ловил рассинхронизацию: успешный
     * POST ротировал токен, а другой носитель оставался со старым → следующий
     * запрос падал с «Invalid security token». Session-bound токен эту проблему
     * убирает и остаётся достаточной защитой.
     */
    public function validateCsrf(string $token): bool
    {
        $stored = $this->session->get('_csrf_token');

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Ротация токена (вход/выход). Ближайший рендер выпустит новый токен.
     */
    public function rotate(): void
    {
        $this->session->remove('_csrf_token');
    }

    public function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
