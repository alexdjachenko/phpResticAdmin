<?php

namespace App\Core;

class Security
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function csrfToken(): string
    {
        $token = $this->session->get('_csrf_token');

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', $token);
        }

        return $token;
    }

    public function validateCsrf(string $token): bool
    {
        $stored = $this->session->get('_csrf_token');

        if ($stored === null || $stored === '') {
            return false;
        }

        $this->session->remove('_csrf_token');

        return hash_equals($stored, $token);
    }

    public function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
