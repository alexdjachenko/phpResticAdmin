<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CanaryTest extends TestCase
{
    public function testWebServerResponds(): void
    {
        $url = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);

        $this->assertNotFalse($response, 'Web server must respond on ' . $url);
        $this->assertStringContainsString('phpResticAdmin', $response ?? '', 'Response must contain "phpResticAdmin"');
    }
}
