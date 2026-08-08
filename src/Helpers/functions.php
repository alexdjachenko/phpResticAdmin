<?php

/**
 * Глобальная функция-хелпер для переводов.
 *
 * @param array<string, string> $replace
 */
function __(string $key, array $replace = []): string {
    return \App\Helpers\Lang::get($key, $replace);
}
