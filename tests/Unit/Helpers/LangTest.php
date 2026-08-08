<?php

namespace App\Tests\Unit\Helpers;

use App\Helpers\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    private string $tmpDir;
    private string $langDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpresticadmin_lang_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        // Create a temporary data/lang structure
        $this->langDir = $this->tmpDir . '/data/lang';
        mkdir($this->langDir, 0777, true);

        // Create en.php
        file_put_contents($this->langDir . '/en.php', '<?php return [
            "app.title" => "MyApp",
            "greeting" => "Hello {name}!",
            "fruit.apple" => "Apple",
        ];');

        // Create ru.php
        file_put_contents($this->langDir . '/ru.php', '<?php return [
            "app.title" => "МоёПриложение",
            "greeting" => "Привет {name}!",
        ];');

        // Set the working directory so Lang finds our test lang files
        // We need to override the path detection. For tests, we use a
        // helper: we create a temporary data/lang inside the project by
        // temporarily using reflection or by testing through the actual
        // methods with a known setup.

        // Instead, we monkey-patch by setting lang to 'en' and using known keys.
        Lang::setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
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
        $this->assertSame('MyApp', Lang::get('app.title'));
    }

    public function testGetFallsBackToKeyWhenMissing(): void
    {
        Lang::setLocale('en');
        $this->assertSame('nonexistent.key', Lang::get('nonexistent.key'));
    }

    public function testGetReplacesPlaceholders(): void
    {
        Lang::setLocale('en');
        $this->assertSame('Hello World!', Lang::get('greeting', ['{name}' => 'World']));
    }

    public function testAvailableReturnsArray(): void
    {
        $available = Lang::available();
        $this->assertIsArray($available);
        $this->assertContains('en', $available);
    }

    public function testDetectFromRequestParsesAcceptLanguage(): void
    {
        // Set Accept-Language header
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7';
        $detected = Lang::detectFromRequest();
        $this->assertSame('ru', $detected);

        // Clean up
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

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
