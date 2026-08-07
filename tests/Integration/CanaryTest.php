<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CanaryTest extends TestCase
{
    public function testWebServerResponds(): void
    {
        $url = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $response = @file_get_contents($url, false, $context);

        $this->assertNotFalse($response, 'Web server must respond on ' . $url);
        $this->assertStringContainsString('phpresticadmin', $response ?? '', 'Response must contain "phpresticadmin"');
        $this->assertStringContainsString('OK', $response ?? '', 'Response must contain "OK"');
    }
}
