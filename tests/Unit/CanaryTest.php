<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CanaryTest extends TestCase
{
    public function testPhpunitWorks(): void
    {
        $this->assertTrue(true, 'PHPUnit canary: tests are running');
    }

    public function testPhpVersionIsAtLeast81(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.1.0', '>='),
            'PHP version must be >= 8.1, got ' . PHP_VERSION
        );
    }

    public function testAppClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Core\App::class), 'App\Core\App class must be autoloadable');
    }
}
