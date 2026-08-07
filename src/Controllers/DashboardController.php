<?php

namespace App\Controllers;

use App\Core\App;

class DashboardController
{
    public function index(): void
    {
        App::response()->redirect('/repositories');
    }
}
