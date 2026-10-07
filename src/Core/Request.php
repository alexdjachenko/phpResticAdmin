<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Core;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path !== null && $path !== false ? $path : '/';
    }

    /**
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $_POST;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Запрос из AJAX/JS (fetch). Используется для выбора JSON вместо редиректа.
     */
    public function isAjax(): bool
    {
        if (strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        $accept = (string) $this->header('Accept');
        return str_contains($accept, 'application/json');
    }
}
