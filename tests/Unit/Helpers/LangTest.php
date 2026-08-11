/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Helpers;

use App\Helpers\Lang;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест Lang (интернационализация).
 *
 * Цель: проверить загрузку переводов, fallback-логику, подстановку
 *       плейсхолдеров, определение языка из Accept-Language.
 *
 * Сценарий:
 *   - setLocale/getLocale: установка и чтение локали.
 *   - get(): перевод существующего ключа на EN и RU.
 *   - fallback: отсутствующий ключ → возвращается сам ключ.
 *   - Плейсхолдеры: {from}, {to} заменяются на значения.
 *   - detectFromRequest: парсинг Accept-Language, fallback на EN.
 *
 * Критерий успеха: все assertSame/assertContains проходят.
 */
class LangTest extends TestCase
{
    protected function setUp(): void
    {
        // Все тесты начинаются с английской локали
        Lang::setLocale('en');
    }

    /** Установка и чтение локали. */
    public function testSetLocaleAndGetLocale(): void
    {
        Lang::setLocale('ru');
        $this->assertSame('ru', Lang::getLocale());

        Lang::setLocale('en');
        $this->assertSame('en', Lang::getLocale());
    }

    /** Перевод существующего ключа (EN). */
    public function testGetReturnsTranslationForKey(): void
    {
        Lang::setLocale('en');
        $this->assertSame('phpResticAdmin', Lang::get('app.title'));
    }

    /** Перевод существующего ключа (RU). */
    public function testGetReturnsRussianTranslation(): void
    {
        Lang::setLocale('ru');
        $this->assertSame('phpResticAdmin', Lang::get('app.title'));
    }

    /** Отсутствующий ключ → возвращается сам ключ. */
    public function testGetFallsBackToKeyWhenMissing(): void
    {
        Lang::setLocale('en');
        $this->assertSame('nonexistent.key', Lang::get('nonexistent.key'));
    }

    /** Плейсхолдеры {from} и {to} заменяются (EN). */
    public function testGetReplacesPlaceholders(): void
    {
        Lang::setLocale('en');
        $this->assertSame(
            'Repository moved from Public to Private.',
            Lang::get('flash.repo_moved', ['{from}' => 'Public', '{to}' => 'Private'])
        );
    }

    /** Плейсхолдеры заменяются и в русской локали. */
    public function testGetReplacesPlaceholdersInRussian(): void
    {
        Lang::setLocale('ru');
        $this->assertSame(
            'Репозиторий перенесён из Общий в Личный.',
            Lang::get('flash.repo_moved', ['{from}' => 'Общий', '{to}' => 'Личный'])
        );
    }

    /**
     * Проверяет, что русская локаль возвращает русский перевод существующего ключа.
     */
    public function testRussianLocaleReturnsRussianTranslation(): void
    {
        Lang::setLocale('ru');
        // Ключ 'nav.login' существует в обоих языках
        $this->assertSame('Вход', Lang::get('nav.login'));
    }

    /** available() возвращает массив с 'en' и 'ru'. */
    public function testAvailableReturnsArray(): void
    {
        $available = Lang::available();
        $this->assertIsArray($available);
        $this->assertContains('en', $available);
        $this->assertContains('ru', $available);
    }

    /** detectFromRequest парсит Accept-Language (первый язык — ru). */
    public function testDetectFromRequestParsesAcceptLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7';
        $detected = Lang::detectFromRequest();
        $this->assertSame('ru', $detected);

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    /** Неизвестный язык → fallback на 'en'. */
    public function testDetectFromRequestFallsBackToEnglish(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';
        $detected = Lang::detectFromRequest();
        $this->assertSame('en', $detected);

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    /** Пустой или отсутствующий заголовок → fallback на 'en'. */
    public function testDetectFromRequestHandlesEmptyHeader(): void
    {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $detected = Lang::detectFromRequest();
        $this->assertSame('en', $detected);
    }
}
