<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Language;

use App\Controllers\BaseController;
use App\Core\App;
use App\Helpers\Lang;

class LanguageController extends BaseController
{
    /**
     * POST /language — переключение языка интерфейса.
     */
    public function switch(): void
    {
        $lang = (string) $this->request()->post('lang', 'en');

        if (in_array($lang, Lang::available(), true)) {
            App::session()->set('lang', $lang);
            Lang::setLocale($lang);
        }

        App::response()->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
