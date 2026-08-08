<?php

namespace App\Helpers;

class Format
{
    /**
     * Форматирует размер в байтах в читаемый вид.
     */
    public static function bytes(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $bytes = (float) $bytes;
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, $precision) . ' ' . $units[$unitIndex];
    }

    /**
     * Форматирует ISO8601 дату restic в заданный формат.
     */
    public static function date(string $iso8601, string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = strtotime($iso8601);
        if ($timestamp === false) {
            return $iso8601;
        }
        return date($format, $timestamp);
    }

    /**
     * Возвращает строку «… ago» для переданной даты.
     */
    public static function timeAgo(string $iso8601): string
    {
        $timestamp = strtotime($iso8601);
        if ($timestamp === false) {
            return $iso8601;
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . ' sec ago';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        }
        if ($diff < 2592000) {
            return floor($diff / 86400) . ' days ago';
        }
        if ($diff < 31536000) {
            return floor($diff / 2592000) . ' months ago';
        }
        return floor($diff / 31536000) . ' years ago';
    }

    /**
     * Обрезает строку до заданной длины с многоточием.
     */
    public static function truncate(string $str, int $maxLen = 50): string
    {
        if (mb_strlen($str) <= $maxLen) {
            return $str;
        }
        return mb_substr($str, 0, $maxLen - 3) . '...';
    }
}
