<?php

namespace App\Core;

class App
{
    public static function boot(): void
    {
    }

    public static function run(): void
    {
        echo "<!DOCTYPE html>\n<html>\n<head><title>phpresticadmin</title></head>\n<body>\n<h1>phpresticadmin v0.0.1 — OK</h1>\n</body>\n</html>";
    }
}
