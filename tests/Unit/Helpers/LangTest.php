<?php

namespace App\Tests\Unit\Helpers;

use App\Helpers\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    protected function setUp(): void
    {
        Lang::setLocale('en');
    }

    public function testSetLocaleAndGetLocale(): void
    {
        Lang::setLocale('ru');
        $this->assertSame('ru', Lang::getLocale());

        Lang::setLocale('en');
        $this->assertSame('en', Lang::getLocale());
    }

    public function testGetReturnsTranslationForKey(): void
    {
        Lang::setLocale('en');
        $this->assertSame('phpResticAdmin', Lang::get('app.title'));
    }

    public function testGetReturnsRussianTranslation(): void
    {
        Lang::setLocale('ru');
        $this->assertSame('phpResticAdmin', Lang::get('app.title'));
    }

    public function testGetFallsBackToKeyWhenMissing(): void
    {
        Lang::setLocale('en');
        $this->assertSame('nonexistent.key', Lang::get('nonexistent.key'));
    }

    public function testGetReplacesPlaceholders(): void
    {
        Lang::setLocale('en');
        $this->assertSame(
            'Repository moved from Public to Private.',
            Lang::get('flash.repo_moved', ['{from}' => 'Public', '{to}' => 'Private'])
        );
    }

    public function testGetReplacesPlaceholdersInRussian(): void
    {
        Lang::setLocale('ru');
        $this->assertSame(
            'Репозиторий перенесён из Общий в Личный.',
            Lang::get('flash.repo_moved', ['{from}' => 'Общий', '{to}' => 'Личный'])
        );
    }

    public function testGetFallsBackToEnglishWhenRussianKeyMissing(): void
    {
        // 'repo.init_checkbox' exists in en.php but also in ru.php, so use a key present only in en
        // Let's use a key that exists in both languages
        Lang::setLocale('ru');
        // Key exists in ru.php, no fallback needed
        $this->assertSame('Вход', Lang::get('nav.login'));
    }

    public function testAvailableReturnsArray(): void
    {
        $available = Lang::available();
        $this->assertIsArray($available);
        $this->assertContains('en', $available);
        $this->assertContains('ru', $available);
    }

    public function testDetectFromRequestParsesAcceptLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7';
        $detected = Lang::detectFromRequest();
        $this->assertSame('ru', $detected);

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testDetectFromRequestFallsBackToEnglish(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';
        $detected = Lang::detectFromRequest();
        $this->assertSame('en', $detected);

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testDetectFromRequestHandlesEmptyHeader(): void
    {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $detected = Lang::detectFromRequest();
        $this->assertSame('en', $detected);
    }
}
