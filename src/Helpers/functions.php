<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

/**
 * Глобальная функция-хелпер для переводов.
 *
 * @param array<string, string> $replace
 */
function __(string $key, array $replace = []): string {
    return \App\Helpers\Lang::get($key, $replace);
}
