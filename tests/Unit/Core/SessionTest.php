<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

namespace App\Tests\Unit\Core;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testSetAndGet(): void
    {
        $session = new Session();
        $session->start();

        $session->set('test_key', 'test_value');
        $this->assertSame('test_value', $session->get('test_key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $session = new Session();
        $session->start();

        $this->assertNull($session->get('nonexistent'));
        $this->assertSame('default', $session->get('nonexistent', 'default'));
    }

    public function testRemove(): void
    {
        $session = new Session();
        $session->start();

        $session->set('key', 'value');
        $session->remove('key');
        $this->assertNull($session->get('key'));
    }

    public function testFlashSetThenGet(): void
    {
        $session = new Session();
        $session->start();

        $session->flash('success', 'Operation completed');
        $this->assertSame('Operation completed', $session->flash('success'));
    }

    public function testFlashSelfDestructsAfterRead(): void
    {
        $session = new Session();
        $session->start();

        $session->flash('info', 'Message');
        $session->flash('info');
        $this->assertNull($session->flash('info'));
    }

    public function testFlashReturnsNullForMissingKey(): void
    {
        $session = new Session();
        $session->start();

        $this->assertNull($session->flash('nonexistent'));
    }

    public function testDestroyClearsAllData(): void
    {
        $session = new Session();
        $session->start();

        $session->set('key1', 'val1');
        $session->set('key2', 'val2');
        $session->destroy();

        $this->assertNull($session->get('key1'));
        $this->assertNull($session->get('key2'));
    }
}
