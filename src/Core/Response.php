<?php

namespace App\Core;

class Response
{
    public function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        $vars['debug'] = App::isDebug();

        $user = App::auth()->user();
        $vars['repositories'] = $user !== null ? App::repoStorage()->loadAll($user) : [];
        $vars['currentRepoId'] = App::session()->get('current_repo');

        if (!isset($vars['isLoggedIn'])) {
            $vars['isLoggedIn'] = App::auth()->isLoggedIn();
        }
        if (!isset($vars['username'])) {
            $vars['username'] = $user;
        }

        // Flash message (читаем один раз, показываем везде)
        $vars['flash'] = $vars['flash'] ?? App::session()->flash('success') ?? App::session()->flash('error');

        // CSRF-токен нужен layout'у для языкового переключателя
        $vars['csrfToken'] = $vars['csrfToken'] ?? App::security()->csrfToken();

        return \App\Helpers\View::render($template, $vars, 'layout.php');
    }

    /**
     * @param mixed $data
     */
    public function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function error(int $code, string $message): never
    {
        http_response_code($code);
        echo '<!DOCTYPE html><html><head><title>Error ' . $code . '</title></head><body><h1>' . $code . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        exit;
    }
}
