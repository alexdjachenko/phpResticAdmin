<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Helpers;

class Lang
{
    /** @var array<string, array<string, string>> */
    private static array $loaded = [];
    private static string $currentLang = 'en';
    private static ?array $currentTranslations = null;
    private static ?array $available = null;

    public static function setLocale(string $lang): void
    {
        self::$currentLang = $lang;
        self::$currentTranslations = self::load($lang);
    }

    public static function getLocale(): string
    {
        return self::$currentLang;
    }

    /**
     * @param array<string, string> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        if (self::$currentTranslations === null) {
            self::$currentTranslations = self::load(self::$currentLang);
        }

        // Пробуем текущий язык
        $value = self::$currentTranslations[$key] ?? null;

        // Fallback на английский
        if ($value === null && self::$currentLang !== 'en') {
            $en = self::load('en');
            $value = $en[$key] ?? null;
        }

        // Fallback на сам ключ
        if ($value === null) {
            $value = $key;
        }

        // Подстановка плейсхолдеров
        if ($replace !== []) {
            $value = strtr($value, $replace);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public static function available(): array
    {
        if (self::$available === null) {
            $langDir = dirname(__DIR__) . '/Lang';
            self::$available = [];

            if (is_dir($langDir)) {
                $files = scandir($langDir);
                foreach ($files as $file) {
                    if (preg_match('/^([a-z]{2})\.php$/', $file, $matches)) {
                        self::$available[] = $matches[1];
                    }
                }
            }

            if (self::$available === []) {
                self::$available = ['en'];
            }
        }

        return self::$available;
    }

    /**
     * Определяет язык из заголовка Accept-Language.
     */
    public static function detectFromRequest(): string
    {
        $available = self::available();
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if ($header === '') {
            return 'en';
        }

        // Парсим: "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7"
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $segment = explode(';', trim($part));
            $tag = trim($segment[0]);
            // Берём первые два символа
            $lang = substr($tag, 0, 2);
            if (in_array($lang, $available, true)) {
                return $lang;
            }
        }

        return 'en';
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $lang): array
    {
        if (isset(self::$loaded[$lang])) {
            return self::$loaded[$lang];
        }

        $path = dirname(__DIR__) . '/Lang/' . $lang . '.php';

        if (!file_exists($path)) {
            self::$loaded[$lang] = [];
            return [];
        }

        /** @var array<string, string> $data */
        $data = require $path;
        self::$loaded[$lang] = is_array($data) ? $data : [];

        return self::$loaded[$lang];
    }
}
