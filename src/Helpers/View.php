<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Helpers;

class View
{
    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $template, array $vars = [], ?string $layout = null): string
    {
        $templatesDir = dirname(__DIR__, 2) . '/templates';

        extract($vars);

        ob_start();
        require $templatesDir . '/' . $template;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = $templatesDir . '/' . $layout;
            if (file_exists($layoutFile)) {
                ob_start();
                require $layoutFile;
                return ob_get_clean();
            }
        }

        return (string) $content;
    }
}
